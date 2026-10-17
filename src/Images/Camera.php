<?php

namespace NineteenNinetyFour\SocialImages\Images;

use NineteenNinetyFour\SocialImages\Core\Rendering\RendererManager;
use NineteenNinetyFour\SocialImages\Core\Rendering\RenderRequest;

/**
 * Takes the screenshot: an HTML page in, PNG bytes out, through the
 * renderer configured in config/social-images.php (see docs/rendering.md).
 *
 * Bound in the container, so tests can swap it; to render another way,
 * add a driver (SocialImages::extend()) rather than replacing this.
 */
class Camera
{
    public function __construct(protected ?RendererManager $renderers = null) {}

    public function capture(string $html, int $width, int $height): string
    {
        $renderers = $this->renderers ?? app(RendererManager::class);

        return $renderers->driver()->render(new RenderRequest($html, $width, $height))->bytes;
    }
}
