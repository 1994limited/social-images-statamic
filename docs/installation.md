# Installation

## Requirements

- Statamic 6.35 or later, PHP 8.3 or later.
- A way to render images: [Browsershot or Cloudflare Browser Rendering](rendering.md).
- Statamic Pro if you want to choose who can design templates (see [Permissions](control-panel.md#permissions)). Everything else works without it.

## Installing

```bash
composer require 1994/social-images-statamic
```

Images are saved to Laravel's `public` disk unless you [choose otherwise](configuration.md#where-images-are-saved), which is served from `/storage` once it's linked:

```bash
php artisan storage:link
```

Then [set up rendering](rendering.md). On your own computer with Chrome installed, Browsershot is the quickest start; on a server without Chrome, use Cloudflare.

## First steps

1. **Make a template.** Go to **Tools → Social Images** and choose **Create template**. Start from one of the seventeen starters and change what you like, or start blank. See [The designer](designer.md).
2. **Choose who uses it.** In **Settings**, add a rule: which collections (or all of them) use which template. See [Choosing templates](choosing-templates.md).
3. **Add the fieldset to your blueprints.** The **Social image** fieldset shows each entry's image in its sidebar, with a button to remake it, and lets an entry choose another template or upload its own image. In a blueprint, add a section to the sidebar and import the fieldset `social-images::social_image`:

    ```yaml
    -
      display: 'Social image'
      fields:
        -
          import: 'social-images::social_image'
    ```

    Or in the blueprint editor: **Add section → Link fieldset → Social image**.
4. **Make the images.** New saves make them as they go. For the entries you have already, go to **Tools → Social Images → Images** and choose **Generate missing**, or run:

    ```bash
    php please social-images:generate
    ```

5. **Put them in your pages.** With SEO Pro, there's nothing to do: see [SEO Pro](seo.md). Without an SEO addon, add the meta tags to your layout's `<head>`:

    ```antlers
    {{ social_image:meta }}
    ```

    <details>
    <summary>Blade</summary>

    ```blade
    <s:social_image:meta />
    ```

    </details>

To check, share a page's address in a private message to yourself, or paste it into a link preview tool. Social networks cache previews: a page shared before it had an image may need refreshing with the network's own tool (Facebook's Sharing Debugger, LinkedIn's Post Inspector).

## Updating

```bash
composer update 1994/social-images-statamic
```

See the [changelog](../CHANGELOG.md). An update that changes how templates are drawn says so, and images are remade the next time each entry is saved or generated.

## Uninstalling

Remove the fieldset from your blueprints, then:

```bash
composer remove 1994/social-images-statamic
```

Your templates stay in `resources/social-images` and the images on their disk until you delete them.
