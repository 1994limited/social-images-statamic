<?php

namespace NineteenNinetyFour\SocialImages\Listeners;

use NineteenNinetyFour\SocialImages\Events\TemplateSaved;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;

/**
 * When a template changes, queue new images for the entries that use it.
 * Their old images stay in use until each new one is ready.
 *
 * Only with a queue worker: on the sync queue every render would run at the
 * end of the save's request, one after another. There, images are remade as
 * pages ask for them, or from the Images page.
 */
class RemakeOnTemplateSave
{
    public function handle(TemplateSaved $event): void
    {
        if (! config('social-images.enabled') || GenerateImage::onSyncQueue()) {
            return;
        }

        $cards = app(Cards::class);
        $handle = $event->template->handle();

        Entry::query()->get()
            ->filter(fn (EntryContract $entry) => $cards->supports($entry) && $cards->templateFor($entry)?->handle() === $handle)
            ->each(function (EntryContract $entry) use ($cards) {
                foreach ($cards->layoutsFor($entry) as $layout) {
                    $card = $cards->for($entry, $layout);

                    if ($card && ! $card->exists()) {
                        GenerateImage::dispatch($entry->id(), $layout, hash: $card->hash());
                    }
                }
            });
    }
}
