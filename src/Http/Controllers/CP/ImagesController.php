<?php

namespace NineteenNinetyFour\SocialImages\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\RateLimits;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Throwable;

/**
 * Tools → Social Images → Images: every entry's image, with buttons to make
 * the missing ones or remake them all, and the endpoints behind the entry
 * form's preview.
 *
 * Bulk runs are driven from the page, one request per image, so each shows
 * as it lands and a long run never meets a request timeout.
 */
class ImagesController extends CpController
{
    public function __construct(Request $request, protected Cards $cards)
    {
        parent::__construct($request);
    }

    public function index(): Response
    {
        $this->authorize('view social images');

        $user = User::current();

        $rows = Entry::query()->get()
            ->filter(fn (EntryContract $entry) => $this->cards->supports($entry) && $this->cards->templateFor($entry))
            ->sortBy(fn (EntryContract $entry) => $entry->collection()->title().'|'.$entry->get('title'))
            ->map(fn (EntryContract $entry) => $this->row($entry, $user->can('edit', $entry) || $user->can('manage social image templates')))
            ->values()
            ->all();

        return Inertia::render('social-images::Images', [
            'entries' => $rows,
            'enabled' => (bool) config('social-images.enabled'),
            'urls' => [
                'templates' => cp_route('social-images.index'),
                'settings' => cp_route('addons.settings.edit', 'social-images-statamic'),
            ],
        ]);
    }

    /**
     * The entry's current image, or a null URL while it's still being made.
     */
    public function show(Request $request, string $entryId): JsonResponse
    {
        $entry = $this->entry($entryId);
        $layout = $this->layout($request);
        $card = $this->cards->for($entry, $layout);
        $override = $this->cards->override($entry);
        $current = $card && $card->exists();

        // Missing or out of date (the template changed, say): start making
        // it, as a page asking for it would. Repeated asks are one render.
        // Not for an entry with its own image (it's never shown), and only
        // for people who could make it anyway.
        $user = User::current();
        $mayMake = $user?->can('edit', $entry) || $user?->can('manage social image templates');

        if ($card && ! $current && ! $override && $mayMake && config('social-images.enabled')) {
            GenerateImage::dispatchWithoutBlocking($entry, $layout);
        }

        $failed = $card && ! $current && GenerateImage::recentlyFailed($entry->id(), $layout, $card->hash());
        $since = $card && ! $current ? $card->renderingSince() : null;

        return response()->json([
            'url' => $current ? $card->previewUrl() : null,
            'previous' => $card && ! $current ? $card->previousPreviewUrl() : null,
            'failed' => $failed,
            // current, failed, rendering (with seconds so far) or queued
            'state' => match (true) {
                ! $card => null,
                $current => 'current',
                $failed => 'failed',
                $since !== null => 'rendering',
                default => 'queued',
            },
            'seconds' => $since !== null ? max(0, time() - $since) : null,
            'override' => $override?->url(),
            'template' => $card?->template->title(),
        ]);
    }

    /**
     * Re-render even when the image looks current: the case this is for is
     * a picture replaced in place, which may leave the fingerprint as it
     * was. Renders here, so the editor sees the new image (or the error)
     * straight away.
     */
    public function regenerate(Request $request, string $entryId): JsonResponse
    {
        $entry = $this->entry($entryId, write: true);
        $layout = $this->layout($request);

        if (! $this->cards->for($entry, $layout)) {
            return response()->json(['message' => __('This entry has no social image template.')], 422);
        }

        try {
            (new GenerateImage($entry->id(), $layout, force: true))->handle();
        } catch (Throwable $exception) {
            report($exception);

            $limited = RateLimits::caused($exception);
            $exhausted = RateLimits::exhausted($exception);

            return response()->json([
                'message' => match (true) {
                    $exhausted => __('The screenshot service’s allowance has run out. Try again once it resets.'),
                    $limited => __('The screenshot service is busy or its limit has been reached. Try again in a minute.'),
                    default => __('The image couldn’t be generated. See the log for details.'),
                },
                'fix' => RateLimits::fix($exception),
                'rate_limited' => $limited,
                'quota' => $exhausted,
                'retry_after' => RateLimits::retryAfter($exception),
                'resets_at' => RateLimits::resetsAt($exception)?->format(DATE_ATOM),
            ], $limited ? 429 : 500);
        }

        return response()->json([
            'message' => __('Image generated'),
            'url' => $this->cards->for($entry, $layout)->previewUrl(),
        ]);
    }

    protected function row(EntryContract $entry, bool $canEdit): array
    {
        $layouts = collect($this->cards->layoutsFor($entry))->map(function ($layout) use ($entry) {
            $card = $this->cards->for($entry, $layout);

            $current = $card->exists();

            return [
                'layout' => $layout,
                'url' => $current ? $card->previewUrl() : null,
                // Out of date: the image still in use until the new one's made.
                'previous' => $current ? null : $card->previousPreviewUrl(),
                'regenerate_url' => cp_route('social-images.images.regenerate', [$entry->id(), 'layout' => $layout]),
            ];
        })->values()->all();

        return [
            'id' => $entry->id(),
            'title' => (string) $entry->get('title'),
            'collection' => $entry->collection()->title(),
            'site' => $entry->locale(),
            'template' => $this->cards->templateFor($entry)?->title(),
            'override' => $this->cards->override($entry)?->url(),
            'edit_url' => $entry->editUrl(),
            'can_edit' => $canEdit,
            'layouts' => $layouts,
        ];
    }

    /**
     * Anyone who can see the entry can see its image; making one takes
     * being able to edit the entry, or to manage the templates.
     */
    protected function entry(string $entryId, bool $write = false): EntryContract
    {
        $entry = Entry::find($entryId);
        $user = User::current();

        abort_unless($entry && $this->cards->supports($entry), 404);
        abort_unless($write
            ? $user?->can('edit', $entry) || $user?->can('manage social image templates')
            : $user?->can('view', $entry) || $user?->can('view social images'), 403);

        return $entry;
    }

    protected function layout(Request $request): string
    {
        return Schema::handle($request->query('layout', $request->input('layout'))) ?? Schema::BASE_LAYOUT;
    }
}
