<?php

namespace NineteenNinetyFour\SocialImages\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use NineteenNinetyFour\SocialImages\Images\Card;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\RateLimits;
use Statamic\Console\RunsInPlease;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Throwable;

/**
 * Makes the images entries don't have yet (or all of them, with --force),
 * one at a time in this process so each is reported as it lands. When the
 * screenshot service turns a render away for going too fast, it waits and
 * tries again; after several refusals in a row it stops, since that usually
 * means a daily allowance has run out. Run it again later to carry on: the
 * images already made are skipped.
 */
class Generate extends Command
{
    use RunsInPlease;

    protected $signature = 'social-images:generate
        {--collection=* : Only entries in these collections}
        {--entry=* : Only these entry IDs}
        {--site= : Only entries in this site}
        {--layout=* : Only these layouts (default: each entry\'s layouts)}
        {--force : Re-render images that already exist}
        {--queue : Push each render to the queue instead of rendering here}';

    protected $description = 'Generate social images for existing entries';

    public function handle(Cards $cards): int
    {
        if (! config('social-images.enabled')) {
            $this->components->warn('Social images are disabled (SOCIAL_IMAGES_ENABLED).');

            return self::FAILURE;
        }

        $jobs = $this->cards($cards);

        if ($jobs->isEmpty()) {
            $this->components->warn('No entries with a template match.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($jobs as $card) {
            $label = $card->entry->collectionHandle().' '.$card->entry->uri().' <fg=gray>'.$card->layout.'</>';

            if (! $this->option('force') && $card->exists()) {
                $this->components->twoColumnDetail($label, '<fg=gray>up to date</>');

                continue;
            }

            if ($this->option('queue')) {
                GenerateImage::dispatch($card->entry->id(), $card->layout, (bool) $this->option('force'), $card->hash());
                $this->components->twoColumnDetail($label, '<fg=blue>queued</>');

                continue;
            }

            try {
                $path = $this->render($card);
                $this->components->twoColumnDetail($label, '<fg=green>'.$path.'</>');
            } catch (RateLimitReached $exception) {
                $this->components->twoColumnDetail($label, '<fg=red>stopped</>');
                $this->components->error('The screenshot service keeps refusing renders: its limit has probably been reached (Cloudflare\'s free plan allows 10 minutes of rendering a day). Run this again later to carry on.');

                return self::FAILURE;
            } catch (Throwable $exception) {
                $failed++;
                $this->components->twoColumnDetail($label, '<fg=red>failed</>');
                $this->components->error($exception->getMessage());
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function render(Card $card, int $attempt = 1): string
    {
        try {
            return $card->render();
        } catch (Throwable $exception) {
            if (! RateLimits::caused($exception)) {
                throw $exception;
            }

            if ($attempt >= max(1, (int) config('social-images.rate_limit_attempts', 4))) {
                throw new RateLimitReached($exception->getMessage(), previous: $exception);
            }

            $wait = (int) config('social-images.rate_limit_wait', 15) * $attempt;
            $this->components->warn("Rate limited, trying again in {$wait}s…");
            sleep($wait);

            return $this->render($card, $attempt + 1);
        }
    }

    /**
     * @return Collection<int, Card>
     */
    protected function cards(Cards $cards): Collection
    {
        $query = Entries::query();

        if ($ids = $this->option('entry')) {
            $query->whereIn('id', $ids);
        }

        if ($collections = $this->option('collection')) {
            $query->whereIn('collection', $collections);
        }

        if ($site = $this->option('site')) {
            $query->where('site', $site);
        }

        $layouts = $this->option('layout');

        return $query->get()
            ->filter(fn (Entry $entry) => $cards->supports($entry))
            ->flatMap(fn (Entry $entry) => collect($layouts ?: $cards->layoutsFor($entry))
                ->map(fn ($layout) => $cards->for($entry, $layout))
                ->filter())
            ->values();
    }
}
