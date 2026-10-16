# Changelog

## 1.0.0 (unreleased)

### Added
- **The designer**: text, image, box and circle layers on a canvas drawn by the same code as the real image. Drag, resize, snap, rotate, undo; gradients, borders, shadows, blur; text that shrinks to fit between two sizes, with a line limit, balanced lines and label-style backgrounds.
- **Fields in text and pictures**: `{title}`, any field, `{author:name}`, `{collection:title}`, `{site:name}`, `{date:j M Y}`, `{url}`, fallbacks with `??`; pictures from entry fields (and related items' fields), cropped around their focal points, or uploaded to the template.
- **Fonts**: eight open-licence fonts included; Google Fonts copied to the site; uploaded fonts. All inlined, so renders load nothing.
- **Sizes**: Open Graph plus square, portrait, Pinterest, story or any size, each with its own layout.
- **Templates as JSON**: export with pictures and fonts inside; import by pasting, uploading or from an address. Every value is checked on the way in. Six starters, also in the gallery.
- **Code templates**: Antlers or Blade views, with `social_image:font`, `social_image:embed` and text fitting.
- **Generating**: on save, queued and deduplicated by a fingerprint of everything the image shows; old images removed. On the `sync` queue, after the response. In bulk from the Images page or `social-images:generate`, waiting out rate limits and stopping at a daily limit.
- **Choosing templates**: rules by collection and site, extra sizes per rule, an entry's own template or image, a fallback image.
- **Rendering** with spatie/laravel-screenshot: Browsershot or Cloudflare Browser Rendering.
- **SEO Pro**: its `og:image` and `twitter:image` use the image. `{{ social_image:meta }}` for sites without an SEO addon.
- **Control Panel**: Templates, the designer, Images, settings, the entry's Social image panel, a listing action, permissions.
