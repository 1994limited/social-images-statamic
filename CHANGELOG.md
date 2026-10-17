# Changelog

## 1.1.2 (unreleased)

### Fixed
- A render that was waiting out a rate limit and then failed for good still showed as queued for a minute or so.

### Documentation
- Code templates: since social-images-core 1.1 the page may load nothing from an address (stylesheets, fonts and images must be inlined with the social_image:font and social_image:embed tags).

## 1.1.1 (2026-10-17)

### Fixed
- A render the screenshot service turned away, waiting on the queue to try again, showed as failed in the entry's panel and on the Images page. It now shows as queued, and page views don't queue it again meanwhile. It's marked failed only once its tries run out.
- A refusal without a wait from the service (a plain 429) now waits out the job's own backoff (15 seconds, then a minute, then five) instead of counting as a failure.

## 1.1.0 (2026-10-17)

### Added
- Renderers: choose how images are rendered with `SOCIAL_IMAGES_RENDERER`. Besides `laravel-screenshot` (the default, as before), Social Images has its own `browsershot` and `cloudflare` drivers, and you can add one with `SocialImages::extend()`. See docs/rendering.md.
- `php please social-images:doctor`, and a Test button under Settings → Rendering: renders a test image and says what to change if it can't.
- A queued render that's turned away waits as long as the service asks, and until the next day when the day's allowance has run out, instead of spending its tries.

### Changed
- The template format, the page renderer, the starters and the fonts now come from the `1994/social-images-core` package, shared with Social Images for other platforms. Nothing changes for your templates or images.
- `Camera` renders through the configured renderer. Binding your own `Camera` still works, but adding a renderer is the better way now.

## 1.0.4 (2026-10-16)

### Changed
- Saving a template queues new images for the entries that use it (with a queue worker). Old images stay in use until each new one is ready.
- The Images page shows an out-of-date image (the one still in use, marked Out of date) instead of "Not generated yet", and its button says Update out of date.

### Documentation
- When images are rendered, with and without a queue worker: a table in docs/rendering.md.

## 1.0.3 (2026-10-16)

### Changed
- A code template's preview on the Templates page shows a recent entry that uses it, instead of a title with nothing else.
- A new Control Panel icon: a share card with a folded corner.

## 1.0.2 (2026-10-16)

### Fixed
- An entry whose image was missing or out of date (after a template change or an update) showed "Generating…" in its panel without anything being made. Opening the entry now starts it, for people who can edit it (and not when the entry has its own image).
- A job queued for an older version of an entry stands down instead of rendering the same image as the newer one.
- The panel keeps checking (less often) for up to ten minutes, for busy queues.

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
