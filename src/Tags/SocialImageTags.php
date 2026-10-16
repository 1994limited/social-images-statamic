<?php

namespace NineteenNinetyFour\SocialImages\Tags;

use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\Rendering\Images;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;
use Statamic\Tags\Tags;

/**
 * {{ social_image }}             the page's image URL
 * {{ social_image }}…{{ /social_image }}   url, width, height, alt, source
 * {{ social_image:meta }}        og:image and twitter:image tags
 * {{ social_image:font }}        @font-face CSS, for code templates
 * {{ social_image:embed }}       a picture as a data URI, for code templates
 *
 * Each takes `entry` (an ID; the current page's by default) and `layout`.
 */
class SocialImageTags extends Tags
{
    protected static $handle = 'social_image';

    public function index(): string|array
    {
        $image = $this->image();

        if ($this->isPair) {
            return $image ?? [];
        }

        return e($image['url'] ?? '');
    }

    public function url(): string
    {
        return e($this->image()['url'] ?? '');
    }

    public function meta(): string
    {
        $image = $this->image();

        if (! $image) {
            return '';
        }

        $tags = ['<meta property="og:image" content="'.e($image['url']).'">'];

        if ($image['width'] && $image['height']) {
            $tags[] = '<meta property="og:image:width" content="'.$image['width'].'">';
            $tags[] = '<meta property="og:image:height" content="'.$image['height'].'">';
        }

        if ($image['alt'] !== '') {
            $tags[] = '<meta property="og:image:alt" content="'.e($image['alt']).'">';
        }

        $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        $tags[] = '<meta name="twitter:image" content="'.e($image['url']).'">';

        return implode("\n", $tags);
    }

    /**
     * @font-face rules for a font from the library, inlined:
     * {{ social_image:font family="Inter" weight="700" }}
     */
    public function font(): string
    {
        $family = Schema::family($this->params->get('family'));

        if (! $family) {
            return '';
        }

        $faces = app(Fonts::class)->embed([[
            'family' => $family,
            'weight' => (int) $this->params->get('weight', 400),
            'style' => $this->params->get('style') === 'italic' ? 'italic' : 'normal',
        ]]);

        return collect($faces)->map(fn ($face) => '@font-face{font-family:"'.$face['family'].'";'
            .'font-style:'.$face['style'].';font-weight:'.$face['weight'].';font-display:block;'
            .'src:url('.$face['src'].');'
            .($face['unicode_range'] ? 'unicode-range:'.$face['unicode_range'].';' : '').'}')
            ->implode("\n");
    }

    /**
     * An asset as a data URI, cropped to the size it's drawn at:
     * {{ social_image:embed :src="featured_image" width="536" height="400" }}
     */
    public function embed(): string
    {
        $src = $this->params->get('src');
        $src = $src instanceof Value ? $src->value() : $src;
        $src = is_iterable($src) ? collect($src)->first() : $src;

        $asset = $src instanceof Asset ? $src : (is_string($src) && str_contains($src, '::') ? AssetFacade::find($src) : null);

        if (! $asset) {
            return '';
        }

        return (string) app(Images::class)->dataUri(
            $asset,
            (int) $this->params->get('width', 1200),
            (int) $this->params->get('height', 630),
            $this->params->get('fit') === 'contain' ? 'contain' : 'cover',
        );
    }

    protected function image(): ?array
    {
        return app(Cards::class)->image($this->entry(), (string) $this->params->get('layout', Schema::BASE_LAYOUT));
    }

    protected function entry(): ?EntryContract
    {
        $id = $this->params->get('entry') ?? $this->context->value('id');

        if ($id instanceof EntryContract) {
            return $id;
        }

        return is_string($id) ? Entry::find($id) : null;
    }
}
