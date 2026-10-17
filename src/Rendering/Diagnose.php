<?php

namespace NineteenNinetyFour\SocialImages\Rendering;

use NineteenNinetyFour\SocialImages\Core\Rendering\Diagnosis;
use NineteenNinetyFour\SocialImages\Core\Rendering\RendererManager;
use Throwable;

/**
 * A test render, for the doctor command and the settings page's Test
 * button. A driver that can't even be made (unknown, or for another
 * contract version) is reported the same way as one that can't render.
 */
class Diagnose
{
    /**
     * @return array{driver: string, ok: bool, message: string, milliseconds: ?int, fix: ?string, capabilities: ?array}
     */
    public static function driver(?string $name = null): array
    {
        $renderers = app(RendererManager::class);
        $name ??= $renderers->defaultDriver();

        try {
            $renderer = $renderers->driver($name);
        } catch (Throwable $exception) {
            return Diagnosis::failed($name, $exception)->toArray() + ['capabilities' => null];
        }

        $capabilities = $renderer->capabilities();

        return $renderer->check()->toArray() + ['capabilities' => [
            'formats' => $capabilities->formats,
            'remote' => $capabilities->remote,
        ]];
    }
}
