# Configuration

Choices about your content are on the settings page; how the addon runs is in `config/social-images.php` and `.env`. Publish the config file to change it:

```bash
php artisan vendor:publish --tag=social-images-config
```

## The settings page

**Tools → Addons → Social Images → Settings**. Saved in `resources/addons/social-images-statamic.yaml`, so it goes with your site's files.

| Setting | |
| --- | --- |
| Templates | The rules: which collections (and sites) use which template, and which extra sizes they make on save. See [Choosing templates](choosing-templates.md). |
| Generate when an entry is saved | On by default. Off: images are made when a page first asks for one, or from the Images page or the command. |
| Fallback image | For pages with no image yet. |
| Rendering | Which renderer is in use, and a Test button that renders a test image. The renderer is chosen in `.env`, not here. |
| SEO Pro | When the social image is used for SEO Pro's tags. See [SEO Pro](seo.md). |

## `config/social-images.php`

### Turning it off

```php
'enabled' => env('SOCIAL_IMAGES_ENABLED', true),
```

See [Turning it off](rendering.md#turning-it-off).

### Where images are saved

```php
'disk' => env('SOCIAL_IMAGES_DISK', 'public'),
'container' => env('SOCIAL_IMAGES_CONTAINER'),
'directory' => 'social-images',
```

Images go to `{directory}/{entry ID}/{size}-{fingerprint}.png` on the disk, which must have a public URL. The default `public` disk is `storage/app/public`, served from `/storage` once `php artisan storage:link` has been run. An S3 or R2 disk works too.

Or set `container` to an asset container's handle: images are saved to its disk, in `directory`, and show in the Assets area like any other asset.

### Templates, fonts and uploads

```php
'path' => resource_path('social-images'),
```

Where the designer keeps its work: `templates/` (one JSON file per template), `fonts/` (fonts you've added, with `fonts.json` listing them) and `uploads/` (pictures uploaded to templates). Commit this folder with your site.

### Code templates

```php
'views' => [],
```

See [Code templates](code-templates.md).

### Queue

```php
'queue' => [
    'connection' => env('SOCIAL_IMAGES_QUEUE_CONNECTION'),
    'name' => env('SOCIAL_IMAGES_QUEUE'),
],
```

See [Queues](rendering.md#queues).

### Rate limits

```php
'rate_limit_wait' => 15,
'rate_limit_attempts' => 4,
```

How long the command and the Images page wait (in seconds, more each time) when the screenshot service says it's busy, and after how many refusals in a row they stop. See [Limits](rendering.md#limits).

### Importing from an address

```php
'import' => [
    'max_kilobytes' => 10240,
    'timeout' => 15,
],
```

The largest template that can be imported from an address or a file, and how long to wait for one.

### Rendering

```php
'renderer' => [
    'driver' => env('SOCIAL_IMAGES_RENDERER', 'laravel-screenshot'),
    'drivers' => [
        'browsershot' => [/* node_binary, npm_binary, chrome_path, node_modules_path, no_sandbox */],
        'cloudflare' => ['api_token' => env('CLOUDFLARE_API_TOKEN'), 'account_id' => env('CLOUDFLARE_ACCOUNT_ID')],
    ],
],
```

Which renderer makes the images, and each one's options. See [Renderers](rendering.md#renderers).

## The screenshot service

With the `laravel-screenshot` renderer (the default), rendering is set up with spatie/laravel-screenshot's own settings, in `.env`: `LARAVEL_SCREENSHOT_DRIVER` and the rest. See [Rendering](rendering.md). To change more, publish its config with `php artisan vendor:publish --tag=laravel-screenshot-config`.
