<?php

namespace NineteenNinetyFour\SocialImages\Actions;

use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use Statamic\Actions\Action;
use Statamic\Contracts\Entries\Entry;

/**
 * "Regenerate social images" in a collection's listing: queues fresh
 * images for the chosen entries, even if theirs look current.
 */
class GenerateSocialImages extends Action
{
    protected static $handle = 'generate_social_images';

    public static function title()
    {
        return __('Regenerate social images');
    }

    public function visibleTo($item)
    {
        return $item instanceof Entry && app(Cards::class)->supports($item) && app(Cards::class)->templateFor($item) !== null;
    }

    public function authorize($user, $item)
    {
        return $user->can('edit', $item);
    }

    public function run($items, $values)
    {
        $count = 0;

        foreach ($items as $entry) {
            foreach (app(Cards::class)->layoutsFor($entry) as $layout) {
                GenerateImage::dispatch($entry->id(), $layout, force: true, hash: app(Cards::class)->for($entry, $layout)?->hash());
                $count++;
            }
        }

        return trans_choice(':count image queued|:count images queued', $count, ['count' => $count]);
    }
}
