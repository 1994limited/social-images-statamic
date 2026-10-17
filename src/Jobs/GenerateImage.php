<?php

namespace NineteenNinetyFour\SocialImages\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\RateLimits;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Throwable;

/**
 * Renders an entry's image in one layout, and removes that layout's older
 * files.
 *
 * Unique per entry, layout *and* fingerprint, so a page viewed again and
 * again before its image exists queues one render, while an edit that
 * changes the image always gets a render of its own, even if an earlier
 * one is still waiting.
 */
class GenerateImage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $uniqueFor = 300;

    public function __construct(
        public string $entryId,
        public string $layout = Schema::BASE_LAYOUT,
        public bool $force = false,
        public ?string $hash = null,
    ) {
        $this->onConnection(config('social-images.queue.connection'));
        $this->onQueue(config('social-images.queue.name'));
    }

    /**
     * Dispatch without holding up the current request. On the sync queue a
     * plain dispatch would render inline, seconds of wait on a page view or
     * a Control Panel save, so it runs once the response has been sent
     * instead. A failure there is reported, not thrown: the response has
     * gone. The lock stands in for ShouldBeUnique, which an after-response
     * run doesn't go through.
     */
    public static function dispatchWithoutBlocking(EntryContract $entry, string $layout = Schema::BASE_LAYOUT): void
    {
        $card = app(Cards::class)->for($entry, $layout);

        if (! $card) {
            return;
        }

        $hash = $card->hash();

        if (self::recentlyFailed($entry->id(), $layout, $hash) || self::waiting($entry->id(), $layout, $hash)) {
            return;
        }

        if (! self::onSyncQueue()) {
            self::dispatch($entry->id(), $layout, hash: $hash);

            return;
        }

        $lock = Cache::lock('social-images:'.$entry->id().':'.$layout.':'.$hash, 300);

        if (! $lock->get()) {
            return;
        }

        app()->terminating(function () use ($entry, $layout, $hash, $lock): void {
            try {
                rescue(fn () => (new self($entry->id(), $layout, hash: $hash))->handle());
            } finally {
                $lock->release();
            }
        });
    }

    /**
     * Whether jobs run inline: the connection images use is the sync driver.
     */
    public static function onSyncQueue(): bool
    {
        $connection = config('social-images.queue.connection') ?? config('queue.default');

        return config("queue.connections.{$connection}.driver", $connection) === 'sync';
    }

    /**
     * A render that just failed isn't tried again from page views for a
     * few minutes, so a broken template or a used-up allowance doesn't
     * cost a render on every visit.
     */
    public static function recentlyFailed(string $entryId, string $layout, string $hash): bool
    {
        return Cache::has(self::failureKey($entryId, $layout, $hash));
    }

    /**
     * A render the service turned away is waiting to try again: page views
     * don't queue another meanwhile, and it shows as queued, not failed.
     */
    public static function waiting(string $entryId, string $layout, string $hash): bool
    {
        return Cache::has(self::waitingKey($entryId, $layout, $hash));
    }

    protected static function waitingKey(string $entryId, string $layout, string $hash): string
    {
        return 'social-images:waiting:'.$entryId.':'.$layout.':'.$hash;
    }

    protected static function failureKey(string $entryId, string $layout, string $hash): string
    {
        return 'social-images:failed:'.$entryId.':'.$layout.':'.$hash;
    }

    public function uniqueId(): string
    {
        return $this->entryId.':'.$this->layout.':'.$this->hash;
    }

    /**
     * Gaps longer than a render, to ride out a per-minute rate limit.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60, 300];
    }

    protected function waitFor(Throwable $exception, ?\DateTimeImmutable $resetsAt): ?int
    {
        if ($resetsAt) {
            return max(60, $resetsAt->getTimestamp() - time());
        }

        return RateLimits::retryAfter($exception);
    }

    /**
     * @return string|null the image's path, or null if the entry has none
     */
    public function handle(): ?string
    {
        $entry = Entry::find($this->entryId);
        $card = $entry ? app(Cards::class)->for($entry, $this->layout) : null;

        if (! $card) {
            return null;
        }

        if (! $this->force && $card->exists()) {
            return $card->path();
        }

        // The entry has changed since this job was queued: the change queued
        // a job of its own for what it shows now, so this one stands down
        // rather than render the same thing twice.
        if (! $this->force && $this->hash !== null && $this->hash !== $card->hash()) {
            return null;
        }

        try {
            $path = $card->render();
        } catch (Throwable $exception) {
            $resetsAt = RateLimits::resetsAt($exception);

            // On a queue, a refusal waits as long as the service asked, or
            // until the allowance resets, rather than spend a try early. It's
            // waiting, not failed, until the tries run out.
            if ($this->job && RateLimits::caused($exception) && $this->attempts() < $this->tries) {
                $wait = $this->waitFor($exception, $resetsAt) ?? ($this->backoff()[$this->attempts() - 1] ?? 300);
                Cache::put(self::waitingKey($this->entryId, $this->layout, $card->hash()), true, $wait + 60);
                $this->release($wait);

                return null;
            }

            // Page views don't try again for a while (until the allowance is
            // back, if it's run out).
            Cache::put(self::failureKey($this->entryId, $this->layout, $card->hash()), true, $resetsAt ?? 300);

            throw $exception;
        }

        Cache::forget(self::failureKey($this->entryId, $this->layout, $card->hash()));
        Cache::forget(self::waitingKey($this->entryId, $this->layout, $card->hash()));

        return $path;
    }
}
