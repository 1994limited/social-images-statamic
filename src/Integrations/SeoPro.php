<?php

namespace NineteenNinetyFour\SocialImages\Integrations;

use Closure;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Settings;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\SeoPro\Tags\SeoProTags;

/**
 * Points SEO Pro's og:image and twitter:image at the entry's social image,
 * so SEO Pro's own meta tags stay the one source of them. Glide is switched
 * off for them: the image is already the right size, and SEO Pro's presets
 * would crop it again.
 *
 * The `seo_pro` setting decides when:
 *  - replace: unless the entry has its own image in its SEO Pro fields
 *  - always:  every entry with a social image
 *  - off:     never; SEO Pro is left as it is
 *
 * While an entry's image is still being made, SEO Pro's own image (from
 * the section or site defaults) stays, if there is one.
 */
class SeoPro
{
    public static function installed(): bool
    {
        return class_exists(SeoProTags::class);
    }

    public static function register(): void
    {
        if (! self::installed()) {
            return;
        }

        SeoProTags::hook('meta-data', function (array $metaData, Closure $next) {
            $id = $this->context->value('id');

            $metaData = app(SeoPro::class)->apply($metaData, is_string($id) ? Entry::find($id) : null);

            return $next($metaData);
        });
    }

    public function apply(array $metaData, ?EntryContract $entry): array
    {
        $mode = app(Settings::class)->get('seo_pro');

        if ($mode === 'off' || ! $entry) {
            return $metaData;
        }

        if ($mode !== 'always' && $this->hasOwnImage($entry)) {
            return $metaData;
        }

        $image = app(Cards::class)->image($entry);

        if (! $image || ($image['source'] === 'fallback' && ! empty($metaData['image']))) {
            return $metaData;
        }

        $metaData['image'] = [
            'permalink' => $image['url'],
            'url' => $image['url'],
            'width' => $image['width'],
            'height' => $image['height'],
            'alt' => $image['alt'],
        ];
        $metaData['is_og_glide_enabled'] = false;
        $metaData['is_twitter_glide_enabled'] = false;

        return $metaData;
    }

    /**
     * An image set in the entry's own SEO Pro fields (not a reference to
     * another field, which is how defaults usually point at one).
     */
    protected function hasOwnImage(EntryContract $entry): bool
    {
        $image = $entry->get('seo')['image'] ?? null;

        return is_string($image) ? $image !== '' && ! str_starts_with($image, '@seo:') : ! empty($image);
    }
}
