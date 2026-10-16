# Code templates

When the designer can't do what you need, write the template yourself: an Antlers or Blade view that's screenshotted just as a designer template is. It gets the entry's values as an entry's template does, so anything Antlers or Blade can do, it can.

## Making one

Put the view anywhere in your views, say `resources/views/social-images/article.antlers.html`, and list it in `config/social-images.php` (publish the config first with `php artisan vendor:publish --tag=social-images-config`):

```php
'views' => [
    'article' => [
        'title' => 'Article',
        'view' => 'social-images/article',
        'layouts' => [
            'og' => [1200, 630],
            'square' => [1080, 1080],
        ],
    ],
],
```

The key (`article`) is its handle; it can't be the same as a designer template's. `layouts` lists its sizes; Open Graph (`og`, 1200 × 630) is added if you leave it out. It then appears on **Tools → Social Images** (marked **Code**) and in the settings' template choices, like any other.

A view is a whole page, with nothing else around it:

```antlers
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    {{ social_image:font family="Fraunces" weight="600" }}
    {{ social_image:font family="Inter" weight="500" }}
    body { margin: 0; width: {{ social_layout:width }}px; height: {{ social_layout:height }}px; background: #1f1a14; color: #f6f1e7; font-family: Inter, sans-serif; }
    .card { display: flex; gap: 64px; align-items: center; height: 100%; padding: 0 80px; box-sizing: border-box; }
    .cover { width: 320px; height: 480px; object-fit: cover; border-radius: 6px; }
    .text { flex: 1; height: 420px; display: flex; flex-direction: column; justify-content: center; }
    h1 { font-family: Fraunces, serif; font-weight: 600; line-height: 1.05; margin: 0 0 24px; }
</style>
</head>
<body>
    <div class="card">
        {{ if cover_image }}
            <img class="cover" src="{{ social_image:embed :src="cover_image" width="320" height="480" }}" alt="">
        {{ /if }}
        <div class="text">
            <h1 data-fit="88" data-fit-min="40" data-fit-lines="3">{{ title }}</h1>
            <p>{{ byline }} · {{ date format="j F Y" }}</p>
        </div>
    </div>
</body>
</html>
```

<details>
<summary>Blade</summary>

```blade
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    {!! Statamic::tag('social_image:font')->params(['family' => 'Fraunces', 'weight' => 600]) !!}
    {!! Statamic::tag('social_image:font')->params(['family' => 'Inter', 'weight' => 500]) !!}
    body { margin: 0; width: {{ $social_layout['width'] }}px; height: {{ $social_layout['height'] }}px; background: #1f1a14; color: #f6f1e7; font-family: Inter, sans-serif; }
    .card { display: flex; gap: 64px; align-items: center; height: 100%; padding: 0 80px; box-sizing: border-box; }
    .cover { width: 320px; height: 480px; object-fit: cover; border-radius: 6px; }
    .text { flex: 1; height: 420px; display: flex; flex-direction: column; justify-content: center; }
    h1 { font-family: Fraunces, serif; font-weight: 600; line-height: 1.05; margin: 0 0 24px; }
</style>
</head>
<body>
    <div class="card">
        @if ($cover_image->value())
            <img class="cover" src="{{ Statamic::tag('social_image:embed')->params(['src' => $cover_image, 'width' => 320, 'height' => 480]) }}" alt="">
        @endif
        <div class="text">
            <h1 data-fit="88" data-fit-min="40" data-fit-lines="3">{{ $title }}</h1>
            <p>{{ $byline }} · {{ $date->format('j F Y') }}</p>
        </div>
    </div>
</body>
</html>
```

</details>

## What a view gets

- **The entry's values**, augmented, as in an entry's template: `title`, every field, `url`, `date`, `collection`, `site`… In Blade they're variables (`$title`), and `$entry` is the entry itself.
- **`social_layout`**: the size being made, with `handle`, `width` and `height`.

## Loading nothing

The page must load nothing over the network: the browser taking the screenshot may not be able to reach your site (Cloudflare can't reach a local site), and anything it waits for slows every image. So fonts and pictures go inside the page:

- **`{{ social_image:font family="…" weight="…" style="italic" }}`** outputs `@font-face` rules for a font from your font library (the included fonts, or ones added in the designer), with the font inside. Use it inside `<style>`. The weight and style choose the nearest the font has.
- **`{{ social_image:embed :src="field" width="…" height="…" fit="contain" }}`** outputs an asset as a `data:` URI, resized by Glide to the size you'll draw it at (cropped around its focal point, or `fit="contain"` to fit inside). `src` takes an assets field's value or an asset ID (`assets::logo.png`).

Write your CSS in the page. Don't link stylesheets, Google Fonts or your site's Vite build.

## Fitting text

Put `data-fit` on an element whose text should shrink to fit its parent: `data-fit="88"` is its largest size in pixels, `data-fit-min="40"` its smallest, and `data-fit-lines="3"` the most lines it may take. The parent needs a fixed size. This is the same fitting as the designer's text layers.

## When it's ready

Social Images adds its small script to the end of the page. Once the fonts and pictures have loaded and text has been fitted, it marks the page ready, and the screenshot is taken. You don't need to do anything for this; if your page does its own work in script, finish it before the page's `load` event.

## When images change

A code template can show anything about an entry, so its images are made again whenever the entry changes at all, or the view's file changes. See [When images change](choosing-templates.md#when-images-change).

## Trying it

**Tools → Social Images** shows each code template with sample text. To see one with an entry and as a real screenshot, open an entry that uses it and choose **Regenerate** in its Social image panel.
