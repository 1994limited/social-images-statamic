<?php

namespace NineteenNinetyFour\SocialImages\Listeners;

use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\Settings;
use Statamic\Events\EntrySaved;

/**
 * Queues fresh images when a save changes what they show. The file name is
 * the image's fingerprint, so if the file already exists the save changed
 * nothing the image cares about and there's nothing to do.
 */
class GenerateOnSave
{
    public function __construct(protected Cards $cards, protected Settings $settings) {}

    public function handle(EntrySaved $event): void
    {
        if (! config('social-images.enabled') || ! $this->settings->bool('generate_on_save')) {
            return;
        }

        foreach ($this->cards->layoutsFor($event->entry) as $layout) {
            $card = $this->cards->for($event->entry, $layout);

            if ($card && ! $card->exists()) {
                GenerateImage::dispatchWithoutBlocking($event->entry, $layout);
            }
        }
    }
}
