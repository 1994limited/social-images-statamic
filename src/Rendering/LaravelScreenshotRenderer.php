<?php

namespace NineteenNinetyFour\SocialImages\Rendering;

use NineteenNinetyFour\SocialImages\Core\Rendering\Capabilities;
use NineteenNinetyFour\SocialImages\Core\Rendering\ChecksByRendering;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\NotConfigured;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\QuotaExhausted;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RateLimited;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RenderFailed;
use NineteenNinetyFour\SocialImages\Core\Rendering\Renderer;
use NineteenNinetyFour\SocialImages\Core\Rendering\RenderRequest;
use NineteenNinetyFour\SocialImages\Core\Rendering\RenderResult;
use Spatie\LaravelScreenshot\Facades\Screenshot;
use Throwable;

/**
 * The renderer Social Images 1.0 used: spatie/laravel-screenshot, set up
 * with its own LARAVEL_SCREENSHOT_* settings (Browsershot or Cloudflare).
 * The default, so sites from 1.0 keep working as they were.
 */
class LaravelScreenshotRenderer implements Renderer
{
    use ChecksByRendering;

    public function capabilities(): Capabilities
    {
        $cloudflare = config('laravel-screenshot.driver') === 'cloudflare';

        return new Capabilities(formats: ['png', 'jpeg', 'webp'], concurrency: $cloudflare ? 3 : null, remote: $cloudflare);
    }

    public function render(RenderRequest $request): RenderResult
    {
        $started = hrtime(true);

        try {
            $base64 = Screenshot::html($request->html)
                ->size($request->width, $request->height)
                ->deviceScaleFactor($request->scale)
                ->waitForSelector($request->readySelector)
                ->base64();
        } catch (Throwable $exception) {
            throw $this->failure($exception);
        }

        return RenderResult::fromBytes(base64_decode($base64), 'image/png', (int) ((hrtime(true) - $started) / 1_000_000));
    }

    /**
     * laravel-screenshot reports errors as text: read what it can.
     */
    protected function failure(Throwable $exception): Throwable
    {
        $message = $exception->getMessage();

        return match (true) {
            stripos($message, 'time limit exceeded') !== false => new QuotaExhausted(__('Cloudflare’s browser time for today has run out.'), new \DateTimeImmutable('tomorrow', new \DateTimeZone('UTC')), previous: $exception),
            (bool) preg_match('/(?<![\w-])429(?![\w-])|rate limit|too many requests/i', $message) => new RateLimited(__('The screenshot service is getting too many renders at once.'), previous: $exception),
            str_contains($message, 'requires both an API token and account ID') => new NotConfigured($message, __('Set CLOUDFLARE_API_TOKEN and CLOUDFLARE_ACCOUNT_ID.'), $exception),
            str_contains($message, 'spatie/browsershot package is required') => new NotConfigured($message, __('Run composer require spatie/browsershot, or switch to Cloudflare.'), $exception),
            default => new RenderFailed($message, previous: $exception),
        };
    }

    protected function driverName(): string
    {
        return 'laravel-screenshot';
    }
}
