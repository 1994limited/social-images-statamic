**Share images for every entry, made for you. Design a card once in the Control Panel, bind it to your entries' titles, photos, authors and dates, and Social Images makes a 1200 × 630 image for each entry when it's saved.**

Your `og:image` and `twitter:image` point at it, so links to your site look like they were designed one by one. It works with SEO Pro, or with no SEO addon at all.

## A visual designer in the Control Panel

Text, images, boxes and circles on a canvas. Drag, resize and snap them; undo anything. Gradients, shadows, borders, rounded corners, rotation and opacity.

## Bound to your entries

Text can show any field: `{title}`, `{subtitle ?? excerpt}`, `{author:name}`, `{date:j M Y}`, `{collection:title}`, `{site:name}`. Images come from the entry's fields, with fallbacks, cropped around each asset's focal point.

## Text that fits

Each text layer shrinks between a largest and smallest size to fit its box, with an optional limit on lines. Long titles stay readable; short ones stay big.

## Fonts

Eight open-licence fonts included. Add any Google Font (copied to your site once) or upload your own.

## Preview with real entries

Pick an entry and the canvas shows its title and photo. Render a real image from the designer, through the same service your site uses.

## Every size

Open Graph (1200 × 630), plus square, portrait, Pinterest, story or any size, each with its own layout in the same template.

## Templates you can share

Export a template as one JSON file, with its images and fonts inside. Import one by pasting it, uploading it, or giving its address. Seventeen starters come with the addon, for blogs, news, shops, events, podcasts, docs, jobs and more.

## Or write your own

Developers can use an Antlers or Blade view as a template, with the same fonts, pictures and text fitting.

## Made in the background, only when needed

An image is made when an entry is saved (or a page first asks for it), and only if what it shows has changed. Pages never wait: until an image exists, they use your fallback image.

## In bulk

Make the missing images, or remake them all, from the Control Panel or with `php please social-images:generate`.

## Per collection, per entry

Choose a template for each collection (and site), let an entry choose another, or upload an image for an entry to use instead.

## No Chrome needed on your server

Render with Browsershot (Chrome on your server) or Cloudflare Browser Rendering (no Chrome at all).

## Requirements

- Statamic 6.35 or later
- PHP 8.3 or later
- Chrome or Chromium and Node.js on the server, or a Cloudflare account
- **Statamic Pro** for Control Panel permissions. Everything else works without it.
