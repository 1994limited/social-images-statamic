# Changelog

## Unreleased

### Fixed
- An entry whose image was missing or out of date (after a template change or an update) showed "Generating…" in its panel without anything being made. Opening the entry now starts it.

### Added
- The entry's panel shows where a render has got to (waiting to start, then rendering with a count of seconds) and the previous image while the new one is made.

## 1.0.1 (2026-10-16)

### Added
- **Twelve more starters**, also in the gallery: Podcast, Newsroom, Event, Product, Recipe, Docs, Hiring, Case study, Portfolio, Quote, Minimal and Magazine, each in Open Graph and square.
- **Picture placeholders** in the designer and in template previews, where an entry's photo will go.
- **Sizes in a menu** in the designer, with Add a size and Remove inside.

### Changed
- Text that fits now takes a slightly smaller size (up to 15%) if it saves a line, so titles wrap as even lines.
- Text that can't fit at its smallest size is cut after the last whole word, with an ellipsis, instead of clipped.
- Sample text formats dates as each token asks, and a fallback chain shows the first field that has a sample.

### Removed
- The Tilted photo starter.

### Fixed
- The last line's descenders (g, y, p) were cut off with tight line heights.
- The designer's canvas no longer picks up the Control Panel's font settings.
- The picture's field menu opens inside the panel.
- Edits made during a save are kept; export waits for the save; slower previews for an earlier entry are dropped.
- A render for an entry's old template can't delete its new image, and pages keep the previous image while a new one is made.
- A failed import stores nothing; a failed Google Fonts download is explained.

## 1.0.0 (2026-10-16)

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
