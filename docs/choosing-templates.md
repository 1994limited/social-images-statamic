# Choosing templates

Which template an entry uses, in order:

1. **The entry's own choice**, in its **Social image** panel (see [Installation](installation.md#first-steps)): another template, or **No image**.
2. **The first rule that matches**, under **Tools → Social Images → Settings**.
3. **None**: the entry gets no generated image, and pages use the [fallback image](#the-fallback-image).

Only entries with a URL get images: an entry in a collection without a route is never shared, so it has none.

## Rules

Each rule has:

- **Collections**: the collections it's for. Empty means every collection.
- **Sites** (on multisite): the sites it's for. Empty means every site.
- **Template**: the template they use.
- **Also make**: extra sizes to make when an entry is saved, beside Open Graph. See [Sizes](#sizes).

Rules are checked from the top; the first one that matches an entry is used. So put particular rules first and a catch-all last:

| Collections | Template |
| --- | --- |
| Books | Book jacket |
| Journal | Editorial |
| *(empty)* | Bold gradient |

## Sizes

Every template has an **Open Graph** layout (1200 × 630), which is what `og:image` and `twitter:image` use and what's made on save. A template can also have other layouts: square, portrait, Pinterest, story, or any size. See [Sizes in the designer](designer.md#sizes).

The other sizes are made when an entry is saved only if its rule ticks them under **Also make**. Otherwise one is made the first time a page asks for it, with `{{ social_image layout="square" }}` (see [Tags](tags.md)). Until it exists, the tag gives the fallback image.

## An entry's own image

The **Use this image instead** field in an entry's Social image panel takes an uploaded image, used in place of the generated one everywhere. 1200 × 630 is the size social networks expect.

## The fallback image

Under **Settings → Generating → Fallback image**, an image for pages that have no social image yet: while an entry's first image is being made (later ones replace the previous image only once they're ready), and for pages with no template (like your home page, if no rule covers it).

With SEO Pro, while an entry's image is being made, SEO Pro's own image (from its site or section defaults) is used if it has one, before this fallback. See [SEO Pro](seo.md).

## When images change

An image's file name is a fingerprint of everything in it: the template, the entry's text and pictures, the fonts. When an entry is saved, Social Images works out the fingerprint again. If a file with that name already exists, the image is still right and nothing happens; if not, a new one is made and the old one deleted.

So:

- Changing a field the image shows, or replacing its picture, or moving the picture's focal point, makes a new image on save.
- Changing a field the image doesn't show makes nothing.
- Changing a template queues new images for every entry that uses it, if you run a queue worker. On the `sync` queue they're made as pages ask for them, as editors open entries, or when you choose **Update out of date** on the Images page (or run `social-images:generate`). Either way, nothing is deleted first: each entry keeps its old image, on its pages and in the Control Panel, until the new one is ready.

Code templates are the exception: a view can show anything about an entry, so any change to the entry (or the view) makes a new image.
