<?php

namespace NineteenNinetyFour\SocialImages\Fieldtypes;

use NineteenNinetyFour\SocialImages\Images\Cards;
use Statamic\Contracts\Entries\Entry;
use Statamic\Fields\Fieldtype;

/**
 * The entry's social image in the publish form, with a button to remake
 * it. Stores nothing.
 *
 * While the image is missing (being made after a save) the Vue side polls
 * the status endpoint until it lands.
 */
class SocialImagePreview extends Fieldtype
{
    protected static $handle = 'social_image_preview';

    protected $categories = ['special'];

    protected $icon = 'image';

    protected $localizable = false;

    protected $validatable = false;

    protected $defaultable = false;

    public function preload(): array
    {
        $entry = $this->field()?->parent();
        $cards = app(Cards::class);
        $enabled = (bool) config('social-images.enabled');

        if (! $entry instanceof Entry || ! $entry->id() || ! $cards->supports($entry) || ! $card = $cards->for($entry)) {
            return ['url' => null, 'status_url' => null, 'regenerate_url' => null, 'template' => null, 'enabled' => $enabled];
        }

        return [
            'url' => $card->exists() ? $card->previewUrl() : null,
            'status_url' => cp_route('social-images.images.show', $entry->id()),
            'regenerate_url' => cp_route('social-images.images.regenerate', $entry->id()),
            'template' => $card->template->title(),
            'width' => $card->width(),
            'height' => $card->height(),
            'enabled' => $enabled,
        ];
    }

    public function process($data): mixed
    {
        return null;
    }

    public function preProcess($data): mixed
    {
        return null;
    }
}
