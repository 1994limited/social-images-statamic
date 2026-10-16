<?php

namespace NineteenNinetyFour\SocialImages\Templates;

use NineteenNinetyFour\SocialImages\Rendering\Page;
use NineteenNinetyFour\SocialImages\Rendering\Tokens;
use NineteenNinetyFour\SocialImages\Support\InSite;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;
use Throwable;

/**
 * A code template: an Antlers or Blade view listed in the config's `views`.
 *
 * The view gets the entry's values, as an entry template would, plus
 * `social_layout` (handle, width and height). It should load nothing over
 * the network: the social_image:font and social_image:embed tags inline
 * fonts and pictures. See docs/code-templates.md.
 */
class ViewTemplate implements Template
{
    public function __construct(
        protected string $handle,
        protected array $config,
    ) {}

    public function handle(): string
    {
        return $this->handle;
    }

    public function title(): string
    {
        return (string) ($this->config['title'] ?? str($this->handle)->headline());
    }

    public function kind(): string
    {
        return 'view';
    }

    public function view(): string
    {
        return (string) ($this->config['view'] ?? '');
    }

    public function layouts(): array
    {
        $layouts = collect($this->config['layouts'] ?? [Schema::BASE_LAYOUT => [1200, 630]])
            ->mapWithKeys(fn ($size, $handle) => [(string) $handle => [
                'width' => max(200, min(2400, (int) ($size['width'] ?? $size[0] ?? 1200))),
                'height' => max(200, min(2400, (int) ($size['height'] ?? $size[1] ?? 630))),
            ]]);

        if (! $layouts->has(Schema::BASE_LAYOUT)) {
            $layouts->prepend(['width' => 1200, 'height' => 630], Schema::BASE_LAYOUT);
        }

        return [Schema::BASE_LAYOUT => $layouts[Schema::BASE_LAYOUT]] + $layouts->all();
    }

    public function hasLayout(string $layout): bool
    {
        return isset($this->layouts()[$layout]);
    }

    public function html(?Entry $entry, string $layout): string
    {
        $size = $this->layouts()[$layout] ?? $this->layouts()[Schema::BASE_LAYOUT];

        $data = $entry
            ? $entry->toAugmentedArray()
            : ['title' => Tokens::SAMPLES['title'], 'site' => Site::default()];

        $html = InSite::run($entry, fn () => view($this->view(), [
            ...$data,
            'entry' => $entry,
            'social_layout' => ['handle' => $layout, ...$size],
        ])->render());

        return app(Page::class)->view($html);
    }

    /**
     * The view's source, the entry's values (its origin's too, on a
     * translation) and where it lives: a code template can show anything
     * about the entry, so any change to it counts. Partials the view
     * includes, and entries, terms or assets it reaches through fields,
     * aren't read: change the template's `version` in the config to remake
     * its images after changing those.
     */
    public function fingerprint(Entry $entry, string $layout): string
    {
        return substr(sha1(json_encode([
            $this->source(),
            $this->config['version'] ?? null,
            $this->layouts()[$layout] ?? null,
            $layout,
            $entry->values()->all(),
            $entry->uri(),
            $entry->locale(),
            $entry->hasDate() ? $entry->date()?->timestamp : null,
            Page::RUNTIME_VERSION,
        ])), 0, 12);
    }

    protected function source(): string
    {
        try {
            return sha1_file(view()->getFinder()->find($this->view())) ?: '';
        } catch (Throwable) {
            return '';
        }
    }
}
