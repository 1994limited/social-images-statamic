<?php

namespace NineteenNinetyFour\SocialImages\Templates;

use NineteenNinetyFour\SocialImages\Rendering\Page;
use NineteenNinetyFour\SocialImages\Rendering\Resolver;
use NineteenNinetyFour\SocialImages\Support\InSite;
use Statamic\Contracts\Entries\Entry;

/**
 * A template made in the designer: a JSON file of layouts and layers (see
 * Schema), drawn by the renderer.
 */
class JsonTemplate implements Template
{
    /**
     * @param  array  $data  normalised by Schema::normalize()
     */
    public function __construct(
        protected string $handle,
        protected array $data,
    ) {}

    public function handle(): string
    {
        return $this->handle;
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function description(): string
    {
        return $this->data['description'] ?? '';
    }

    public function kind(): string
    {
        return 'designer';
    }

    public function layouts(): array
    {
        return collect($this->data['layouts'])
            ->map(fn ($layout) => ['width' => $layout['width'], 'height' => $layout['height']])
            ->all();
    }

    public function hasLayout(string $layout): bool
    {
        return isset($this->data['layouts'][$layout]);
    }

    public function layout(string $layout): array
    {
        return $this->data['layouts'][$layout] ?? $this->data['layouts'][Schema::BASE_LAYOUT];
    }

    public function html(?Entry $entry, string $layout): string
    {
        $layout = $this->layout($layout);

        return InSite::run($entry, fn () => app(Page::class)->designer($layout, app(Resolver::class)->resolve($layout, $entry), $this->lang($entry)));
    }

    public function fingerprint(Entry $entry, string $layout): string
    {
        return InSite::run($entry, fn () => app(Resolver::class)->fingerprint($this->layout($layout), $entry));
    }

    /**
     * Every upload the template's layers use.
     *
     * @return array<int, string>
     */
    public function uploads(): array
    {
        return collect($this->data['layouts'])
            ->flatMap(fn ($layout) => $layout['layers'])
            ->where('type', 'image')
            ->flatMap(fn ($layer) => $layer['source'])
            ->filter(fn ($source) => str_starts_with($source, 'upload:'))
            ->map(fn ($source) => substr($source, 7))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every font family the template's text uses.
     *
     * @return array<int, string>
     */
    public function families(): array
    {
        return collect($this->data['layouts'])
            ->flatMap(fn ($layout) => $layout['layers'])
            ->where('type', 'text')
            ->map(fn ($layer) => $layer['font']['family'])
            ->unique()
            ->values()
            ->all();
    }

    public function toArray(): array
    {
        return $this->data;
    }

    protected function lang(?Entry $entry): string
    {
        return $entry ? (string) $entry->site()->lang() : 'en';
    }
}
