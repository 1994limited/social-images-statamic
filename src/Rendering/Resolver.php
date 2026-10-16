<?php

namespace NineteenNinetyFour\SocialImages\Rendering;

use NineteenNinetyFour\SocialImages\Library\Fonts;
use Statamic\Contracts\Entries\Entry;

/**
 * Works out what each layer of a designer layout shows for an entry: the
 * text with its tokens filled in, the picture, or that it's hidden. With no
 * entry, tokens show sample text (the designer's view before one is picked).
 */
class Resolver
{
    public function __construct(
        protected Tokens $tokens,
        protected Images $images,
        protected Fonts $fonts,
    ) {}

    /**
     * The data the renderer draws the layout with.
     *
     * @return array{layers: array<string, array>, fonts: array, categories: array<string, string>}
     */
    public function resolve(array $layout, ?Entry $entry): array
    {
        $layers = [];

        foreach ($this->bindings($layout, $entry) as $id => $binding) {
            $layers[$id] = match (true) {
                array_key_exists('text', $binding) => array_filter(['text' => $binding['text'], 'hidden' => $binding['hidden']]),
                $binding['hidden'] => ['hidden' => true],
                default => ['src' => $this->images->dataUri($binding['image'], (int) ceil($binding['width']), (int) ceil($binding['height']), $binding['fit'])],
            };
        }

        $fonts = $this->fontsUsed($layout);

        return [
            'layers' => $layers,
            'fonts' => $this->fonts->embed($fonts),
            'categories' => collect($this->fonts->families())->pluck('category', 'family')->all(),
        ];
    }

    /**
     * What identifies this layout's image for an entry: the layout itself,
     * each layer's text and picture, the font files and the renderer.
     */
    public function fingerprint(array $layout, Entry $entry): string
    {
        $bindings = collect($this->bindings($layout, $entry))->map(fn ($binding) => array_key_exists('image', $binding)
            ? [$binding['hidden'], $this->images->identity($binding['image'])]
            : [$binding['hidden'], $binding['text'] ?? null]);

        return substr(sha1(json_encode([
            $layout,
            $bindings,
            $this->fonts->fingerprint($this->fontsUsed($layout)),
            Page::RUNTIME_VERSION,
        ])), 0, 12);
    }

    /**
     * @return array<string, array{hidden: bool, text?: string, image?: mixed, width?: float, height?: float, fit?: string}>
     */
    protected function bindings(array $layout, ?Entry $entry): array
    {
        $bindings = [];

        foreach ($layout['layers'] as $layer) {
            if ($layer['hidden'] ?? false) {
                continue;
            }

            $shown = $this->tokens->filled($layer['when'] ?? null, $entry);

            if ($layer['type'] === 'text') {
                $text = $shown ? $this->tokens->text($layer['text'], $entry) : '';
                $bindings[$layer['id']] = ['hidden' => trim($text) === '', 'text' => $text];
            } elseif ($layer['type'] === 'image') {
                $image = $shown ? $this->images->find($layer['source'], $entry) : null;
                $bindings[$layer['id']] = [
                    'hidden' => $image === null,
                    'image' => $image,
                    'width' => $layer['width'],
                    'height' => $layer['height'],
                    'fit' => $layer['fit'],
                ];
            } elseif (! $shown) {
                $bindings[$layer['id']] = ['hidden' => true];
            }
        }

        return $bindings;
    }

    /**
     * @return array<int, array{family: string, weight: int, style: string}>
     */
    public function fontsUsed(array $layout): array
    {
        return collect($layout['layers'])
            ->where('type', 'text')
            ->map(fn ($layer) => $layer['font'])
            ->unique(fn ($font) => $font['family'].$font['weight'].$font['style'])
            ->values()
            ->all();
    }
}
