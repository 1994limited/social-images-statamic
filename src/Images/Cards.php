<?php

namespace NineteenNinetyFour\SocialImages\Images;

use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\Settings;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use NineteenNinetyFour\SocialImages\Templates\Template;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Fields\Value;

/**
 * Which image an entry gets, and where to find it.
 *
 * In order: the image uploaded to the entry's own `social_image` field;
 * the image generated from its template; and, while that's still being
 * made (or for pages with no template), the fallback image from settings.
 */
class Cards
{
    /** The entry field holding an uploaded image to use instead. */
    public const OVERRIDE_FIELD = 'social_image_override';

    /** The entry field choosing a template ("none" for no image). */
    public const TEMPLATE_FIELD = 'social_image_template';

    public function __construct(
        protected TemplateRepository $templates,
        protected Settings $settings,
        protected Output $output,
    ) {}

    /**
     * Only entries with a URL are shared, so only they get images.
     */
    public function supports(Entry $entry): bool
    {
        return $entry->url() !== null && $entry->id() !== null;
    }

    public function templateFor(Entry $entry): ?Template
    {
        $chosen = $entry->get(self::TEMPLATE_FIELD);

        if ($chosen === 'none') {
            return null;
        }

        if (is_string($chosen) && $template = $this->templates->find($chosen)) {
            return $template;
        }

        return $this->templates->find($this->rule($entry)['template'] ?? null);
    }

    /**
     * The layouts made when the entry is saved: the base layout, and the
     * extras its settings rule names that the template has.
     *
     * @return array<int, string>
     */
    public function layoutsFor(Entry $entry): array
    {
        $template = $this->templateFor($entry);

        if (! $template) {
            return [];
        }

        return collect([Schema::BASE_LAYOUT, ...($this->rule($entry)['layouts'] ?? [])])
            ->filter(fn ($layout) => $template->hasLayout($layout))
            ->unique()
            ->values()
            ->all();
    }

    public function for(Entry $entry, string $layout = Schema::BASE_LAYOUT): ?Card
    {
        if (! $this->supports($entry) || ! $template = $this->templateFor($entry)) {
            return null;
        }

        return $template->hasLayout($layout) ? new Card($entry, $template, $layout) : null;
    }

    /**
     * The image uploaded to the entry to use instead of a generated one.
     */
    public function override(Entry $entry): ?Asset
    {
        // An assets field with no container to look in throws.
        $value = rescue(fn () => $entry->augmentedValue(self::OVERRIDE_FIELD), null, false);
        $value = $value instanceof Value ? rescue(fn () => $value->value(), null, false) : $value;
        $value = is_iterable($value) ? collect($value)->first() : $value;

        return $value instanceof Asset ? $value : null;
    }

    /**
     * The share image for a page, in the shape meta tags need. An entry
     * whose image hasn't been made yet gets the fallback this time, and its
     * image is queued: the page never waits for a render.
     *
     * @return array{url: string, width: ?int, height: ?int, alt: string, source: string}|null
     */
    public function image(?Entry $entry, string $layout = Schema::BASE_LAYOUT): ?array
    {
        if ($entry && ($override = $this->override($entry)) && $override->absoluteUrl()) {
            return [
                'url' => $override->absoluteUrl(),
                'width' => $override->width() ? (int) $override->width() : null,
                'height' => $override->height() ? (int) $override->height() : null,
                'alt' => (string) ($override->get('alt') ?: $entry->get('title')),
                'source' => 'override',
            ];
        }

        if ($entry && $card = $this->for($entry, $layout)) {
            if ($card->exists()) {
                return [
                    'url' => $card->url(),
                    'width' => $card->width(),
                    'height' => $card->height(),
                    'alt' => (string) $entry->get('title'),
                    'source' => 'generated',
                ];
            }

            if (config('social-images.enabled')) {
                GenerateImage::dispatchWithoutBlocking($entry, $layout);
            }
        }

        return $this->fallback();
    }

    /**
     * @return array{url: string, width: ?int, height: ?int, alt: string, source: string}|null
     */
    public function fallback(): ?array
    {
        $value = $this->settings->get('fallback_image');
        $value = is_array($value) ? ($value[0] ?? null) : $value;

        if (! is_string($value) || $value === '') {
            return null;
        }

        $asset = AssetFacade::find(str_contains($value, '::') ? $value : 'assets::'.$value);

        if (! $asset) {
            return null;
        }

        return [
            'url' => $asset->absoluteUrl(),
            'width' => $asset->width() ? (int) $asset->width() : null,
            'height' => $asset->height() ? (int) $asset->height() : null,
            'alt' => (string) ($asset->get('alt') ?: config('app.name')),
            'source' => 'fallback',
        ];
    }

    /**
     * Remove every image an entry has.
     */
    public function forget(Entry $entry): void
    {
        $this->output->deleteDirectory($this->output->directory().'/'.$entry->id());
    }

    /**
     * The first settings rule for the entry's collection and site.
     */
    protected function rule(Entry $entry): ?array
    {
        return collect($this->settings->rules())->first(fn ($rule) => (! $rule['collections'] || in_array($entry->collectionHandle(), $rule['collections'], true))
            && (! $rule['sites'] || in_array($entry->locale(), $rule['sites'], true)));
    }
}
