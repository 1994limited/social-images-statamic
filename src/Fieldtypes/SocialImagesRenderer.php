<?php

namespace NineteenNinetyFour\SocialImages\Fieldtypes;

use NineteenNinetyFour\SocialImages\Core\Rendering\RendererManager;
use Statamic\Fields\Fieldtype;

/**
 * The settings page's Rendering section: which renderer is in use (set in
 * .env, not here, as it can need secrets) and a button to test it. Stores
 * nothing.
 */
class SocialImagesRenderer extends Fieldtype
{
    protected static $handle = 'social_images_renderer';

    protected $selectable = false;

    protected $localizable = false;

    protected $validatable = false;

    protected $defaultable = false;

    public function preload(): array
    {
        $renderers = app(RendererManager::class);

        return [
            'driver' => $renderers->defaultDriver(),
            'check_url' => cp_route('social-images.renderer.check'),
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
