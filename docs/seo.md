# SEO Pro

With [SEO Pro](https://statamic.com/addons/statamic/seo-pro) installed, Social Images gives SEO Pro the entry's image for its `og:image` and `twitter:image` tags. There's nothing to add to your templates: SEO Pro's `{{ seo_pro:meta }}` (or `<s:seo_pro:meta />`) already outputs them.

It works through SEO Pro's `meta-data` hook, and turns off SEO Pro's own resizing for these images, since they're already the right size. Checked with SEO Pro 7.15.

## When

Under **Settings → SEO Pro**:

- **Unless the entry has its own SEO image** (the default): an image set in an entry's own SEO Pro fields wins; otherwise the social image is used, in place of SEO Pro's site or section default.
- **Always**: the social image is used for every entry that has one, whatever its SEO Pro fields say.
- **Never**: SEO Pro is left alone. Use the [tags](tags.md) wherever you like.

An image set in SEO Pro's section defaults that points at a field (`@seo:featured_image`) doesn't count as the entry's own: the social image is used.

While an entry's new image is being made, its previous one is used. An entry that has never had one keeps SEO Pro's own image (from its site or section defaults) if there is one, and the [fallback image](choosing-templates.md#the-fallback-image) otherwise.

For pages that aren't entries (taxonomy pages, routes of your own), SEO Pro works as it did.

## Other SEO addons

With another SEO addon, turn off its image tags (or leave its image fields empty) and add [`{{ social_image:meta }}`](tags.md#the-meta-tags) to your layout's `<head>`. Using one we should work with directly? Tell us at [hello@1994.co.uk](mailto:hello@1994.co.uk).
