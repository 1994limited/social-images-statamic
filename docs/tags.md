# Tags

With [SEO Pro](seo.md) you may not need any of these: it puts the image in its own meta tags. They're for sites without an SEO addon, and for showing or linking to an image yourself.

Each takes `entry` (an entry's ID; the current page's entry by default) and `layout` (`og` by default; any size the template has, like `square`).

## The meta tags

In your layout's `<head>`:

```antlers
{{ social_image:meta }}
```

<details>
<summary>Blade</summary>

```blade
<s:social_image:meta />
```

</details>

gives

```html
<meta property="og:image" content="https://example.com/storage/social-images/…/og-1c2d3e4f5a6b.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="The entry's title">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://example.com/storage/social-images/…/og-1c2d3e4f5a6b.png">
```

on an entry's page, and nothing on a page with no image and no [fallback](choosing-templates.md#the-fallback-image). Add your own `og:title`, `og:description` and so on beside them.

## The image's address

```antlers
{{ social_image }}
```

<details>
<summary>Blade</summary>

```blade
<s:social_image />
```

</details>

As a pair, for its size and more:

```antlers
{{ social_image layout="square" }}
    <img src="{{ url }}" width="{{ width }}" height="{{ height }}" alt="{{ alt }}">
{{ /social_image }}
```

<details>
<summary>Blade</summary>

```blade
<s:social_image layout="square">
    <img src="{{ $url }}" width="{{ $width }}" height="{{ $height }}" alt="{{ $alt }}">
</s:social_image>
```

</details>

| Variable | |
| --- | --- |
| `url` | The image's full address |
| `width`, `height` | Its size in pixels (empty for a fallback or uploaded image whose size isn't known) |
| `alt` | The entry's title, or the uploaded image's alt text |
| `source` | `generated`, `override` (the entry's own image) or `fallback` |

For another entry, give its ID: `{{ social_image :entry="id" }}` in a listing, or `<s:social_image :entry="$id" />` in Blade.

## Which image

The entry's own image if it has one, otherwise its generated image, otherwise the fallback. When an entry's image needs making again (it was just changed), pages keep the image it replaces until the new one is ready; an entry that has never had one (or a size that's never been asked for) gets the fallback. Either way the new image is made in the background for the next visitor, and the page never waits. See [Choosing templates](choosing-templates.md).

## In code templates

`social_image:font` and `social_image:embed` are for [code templates](code-templates.md#loading-nothing).
