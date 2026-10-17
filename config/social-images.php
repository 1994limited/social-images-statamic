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
    */

    'enabled' => env('SOCIAL_IMAGES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    |
    | What turns each image's page into a PNG. One of:
    |
    | laravel-screenshot  spatie/laravel-screenshot, set up with its own
    |                     LARAVEL_SCREENSHOT_* settings (what 1.0 used)
    | browsershot         Chrome on this server, through Browsershot
    | cloudflare          Cloudflare Browser Rendering
    |
    | or one you've added with SocialImages::extend(). Options for each go
    | under `drivers`. Check the one in use with php please social-images:doctor.
    | See docs/rendering.md.
    |
    */

    'renderer' => [
        'driver' => env('SOCIAL_IMAGES_RENDERER', 'laravel-screenshot'),

        'drivers' => [
            'browsershot' => [
                'node_binary' => env('SOCIAL_IMAGES_NODE_BINARY', env('LARAVEL_SCREENSHOT_NODE_BINARY')),
                'npm_binary' => env('SOCIAL_IMAGES_NPM_BINARY', env('LARAVEL_SCREENSHOT_NPM_BINARY')),
                'chrome_path' => env('SOCIAL_IMAGES_CHROME_PATH', env('LARAVEL_SCREENSHOT_CHROME_PATH')),
                'node_modules_path' => env('SOCIAL_IMAGES_NODE_MODULES_PATH', env('LARAVEL_SCREENSHOT_NODE_MODULES_PATH')),
                'no_sandbox' => env('SOCIAL_IMAGES_NO_SANDBOX', env('LARAVEL_SCREENSHOT_NO_SANDBOX', false)),
            ],

            'cloudflare' => [
                'api_token' => env('CLOUDFLARE_API_TOKEN'),
                'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
            ],
        ],
    ],

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
