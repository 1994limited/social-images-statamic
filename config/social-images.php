<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Generating images
    |--------------------------------------------------------------------------
    |
    | When disabled, nothing is generated on save or when a page is viewed,
    | and pages use the fallback image. Images already made are kept.
    |
    | Images are rendered with spatie/laravel-screenshot, using the driver
    | LARAVEL_SCREENSHOT_DRIVER sets ("browsershot" or "cloudflare"). See
    | docs/rendering.md for each one's setup.
    |
    */

    'enabled' => env('SOCIAL_IMAGES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Where images are saved
    |--------------------------------------------------------------------------
    |
    | A filesystem disk and a folder on it. The disk must have a public URL:
    | the default "public" disk is storage/app/public, served from /storage
    | once `php artisan storage:link` has been run.
    |
    | Or set `container` to an asset container's handle, and images are saved
    | to its disk, in `directory`, and show in the Assets area.
    |
    */

    'disk' => env('SOCIAL_IMAGES_DISK', 'public'),

    'container' => env('SOCIAL_IMAGES_CONTAINER'),

    'directory' => 'social-images',

    /*
    |--------------------------------------------------------------------------
    | Templates, fonts and uploads
    |--------------------------------------------------------------------------
    |
    | Templates made in the designer are JSON files here, with the fonts and
    | images they use, so they can be committed with the rest of the site.
    |
    */

    'path' => resource_path('social-images'),

    /*
    |--------------------------------------------------------------------------
    | Code templates
    |--------------------------------------------------------------------------
    |
    | Antlers or Blade views to use as templates, beside the ones made in the
    | designer. Each is keyed by a handle:
    |
    | 'views' => [
    |     'article' => [
    |         'title' => 'Article',
    |         'view' => 'social-images/article',
    |         'layouts' => ['og' => [1200, 630], 'square' => [1080, 1080]],
    |     ],
    | ],
    |
    | See docs/code-templates.md.
    |
    */

    'views' => [],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | The connection and queue the render jobs are pushed to. Null uses the
    | defaults. On the sync queue, images render after the response is sent.
    |
    */

    'queue' => [
        'connection' => env('SOCIAL_IMAGES_QUEUE_CONNECTION'),
        'name' => env('SOCIAL_IMAGES_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    |
    | Seconds to wait before trying again when the screenshot service turns a
    | render away for going too fast (Cloudflare's free plan, for one). The
    | generate command gives up after `rate_limit_attempts` tries in a row,
    | which usually means a daily allowance has run out.
    |
    */

    'rate_limit_wait' => 15,

    'rate_limit_attempts' => 4,

    /*
    |--------------------------------------------------------------------------
    | Importing templates from a URL
    |--------------------------------------------------------------------------
    |
    | Only https URLs on public addresses are fetched, and no more than this
    | many kilobytes.
    |
    */

    'import' => [
        'max_kilobytes' => 10240,
        'timeout' => 15,
    ],

];
