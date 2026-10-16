<?php

namespace NineteenNinetyFour\SocialImages\Images;

use Spatie\LaravelScreenshot\Facades\Screenshot;

/**
 * Takes the screenshot: an HTML page in, PNG bytes out. Uses
 * spatie/laravel-screenshot with whichever driver LARAVEL_SCREENSHOT_DRIVER
 * sets, and waits for the page to mark itself ready.
 *
 * Bound in the container, so tests (and anyone with their own renderer)
 * can swap it.
 */
class Camera
{
    public function capture(string $html, int $width, int $height): string
    {
        $base64 = Screenshot::html($html)
            ->size($width, $height)
            ->deviceScaleFactor(1)
            ->waitForSelector('html[data-social-ready]')
            ->base64();

        return base64_decode($base64);
    }
}
