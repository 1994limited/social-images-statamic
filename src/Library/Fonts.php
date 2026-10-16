<?php

namespace NineteenNinetyFour\SocialImages\Library;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use NineteenNinetyFour\SocialImages\Templates\Schema;

/**
 * The fonts templates can use.
 *
 * Two kinds: the open-licence fonts that come with the addon
 * (resources/fonts), and the site's own, added in the designer from Google
 * Fonts or uploaded, kept in the fonts folder beside the templates with a
 * fonts.json listing them. Every font reaches the renderer as a data URI,
 * so a render never fetches one.
 *
 * A "face" is one file: a family, a style, a weight or range of weights,
 * and optionally the characters it covers (Google Fonts and the bundled
 * fonts are split into Latin and Latin Extended files).
 *
 * @phpstan-type Face array{id: string, family: string, style: string, weight: array{0: int, 1: int}, unicode_range: ?string, file: string, source: string}
 */
class Fonts
{
    public const TYPES = [
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    public const MAX_BYTES = 5 * 1024 * 1024;

    protected ?array $bundled = null;

    protected ?array $saved = null;

    public function __construct(protected Filesystem $files) {}

    public function directory(): string
    {
        return rtrim((string) config('social-images.path'), '/').'/fonts';
    }

    /**
     * Every face, bundled first.
     *
     * @return Collection<int, array>
     */
    public function faces(): Collection
    {
        return collect($this->bundled())->merge($this->saved());
    }

    /**
     * One row per family for the designer's font menu.
     *
     * @return array<int, array{family: string, source: string, category: string, weights: array<int, int>, italic: bool}>
     */
    public function families(): array
    {
        return $this->faces()
            ->groupBy('family')
            ->map(function (Collection $faces, string $family) {
                $weights = $faces->flatMap(fn ($face) => range($face['weight'][0], $face['weight'][1], 100))
                    ->map(fn ($weight) => (int) (round($weight / 100) * 100))
                    ->filter(fn ($weight) => $weight >= 100 && $weight <= 900)
                    ->unique()->sort()->values()->all();

                return [
                    'family' => $family,
                    'source' => $faces->first()['source'],
                    'category' => $faces->first()['category'] ?? 'sans-serif',
                    'weights' => $weights,
                    'italic' => $faces->contains('style', 'italic'),
                ];
            })
            ->sortBy(fn ($family) => ($family['source'] === 'bundled' ? '1' : '0').strtolower($family['family']))
            ->values()
            ->all();
    }

    public function has(string $family): bool
    {
        return $this->faces()->contains('family', $family);
    }

    public function isBundled(string $family): bool
    {
        return collect($this->bundled())->contains('family', $family);
    }

    /**
     * The faces that draw this family at this weight and style: one per
     * character range, closest weight first. Falls back to the upright
     * style when there's no italic.
     */
    public function match(string $family, int $weight = 400, string $style = 'normal'): Collection
    {
        $faces = $this->faces()->where('family', $family);

        if ($style === 'italic' && ! $faces->contains('style', 'italic')) {
            $style = 'normal';
        }

        $faces = $faces->where('style', $style);

        $distance = fn ($face) => $weight < $face['weight'][0]
            ? $face['weight'][0] - $weight
            : max(0, $weight - $face['weight'][1]);

        $best = $faces->min($distance);

        return $faces->filter(fn ($face) => $distance($face) === $best)
            ->unique(fn ($face) => $face['unicode_range'] ?? '')
            ->values();
    }

    /**
     * What the renderer needs to load these fonts, with their files inlined.
     *
     * @param  array<int, array{family: string, weight: int, style: string}>  $fonts
     * @return array<int, array{family: string, style: string, weight: string, src: string, unicode_range: ?string}>
     */
    public function embed(array $fonts): array
    {
        return collect($fonts)
            ->flatMap(fn ($font) => $this->match($font['family'], (int) $font['weight'], $font['style']))
            ->unique('id')
            ->map(fn ($face) => [
                'family' => $face['family'],
                'style' => $face['style'],
                'weight' => $face['weight'][0] === $face['weight'][1] ? (string) $face['weight'][0] : $face['weight'][0].' '.$face['weight'][1],
                'src' => $this->dataUri($face),
                'unicode_range' => $face['unicode_range'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * A short hash of the files behind these fonts, for image fingerprints.
     */
    public function fingerprint(array $fonts): string
    {
        return sha1(collect($fonts)
            ->flatMap(fn ($font) => $this->match($font['family'], (int) $font['weight'], $font['style']))
            ->pluck('id')->unique()->sort()->implode(','));
    }

    public function dataUri(array $face): string
    {
        $path = $this->absolute($face);
        $type = self::TYPES[pathinfo($path, PATHINFO_EXTENSION)] ?? 'font/woff2';

        return 'data:'.$type.';base64,'.base64_encode($this->files->get($path));
    }

    /**
     * Add one font file to the site's fonts.
     *
     * @param  array{0: int, 1: int}|int  $weight
     *
     * @throws InvalidArgumentException
     */
    public function add(string $family, string $contents, string $extension, array|int $weight = 400, string $style = 'normal', ?string $unicodeRange = null, string $source = 'upload'): array
    {
        $family = Schema::family($family) ?? throw new InvalidArgumentException(__('Font names can use letters, numbers, spaces, dots, dashes and ampersands.'));
        $extension = strtolower($extension);

        if (! isset(self::TYPES[$extension])) {
            throw new InvalidArgumentException(__('Fonts must be WOFF2, WOFF, TTF or OTF files.'));
        }

        if (strlen($contents) > self::MAX_BYTES) {
            throw new InvalidArgumentException(__('Fonts must be smaller than 5 MB.'));
        }

        $magic = substr($contents, 0, 4);

        if (! match ($extension) {
            'woff2' => $magic === 'wOF2',
            'woff' => $magic === 'wOFF',
            'ttf', 'otf' => in_array($magic, ["\x00\x01\x00\x00", 'OTTO', 'true'], true),
        }) {
            throw new InvalidArgumentException(__('That file isn’t the kind of font its name says it is.'));
        }

        if ($this->isBundled($family)) {
            throw new InvalidArgumentException(__(':family comes with Social Images already.', ['family' => $family]));
        }

        $weight = is_array($weight) ? $weight : [$weight, $weight];
        $weight = [max(100, min(1000, (int) $weight[0])), max(100, min(1000, (int) ($weight[1] ?? $weight[0])))];
        sort($weight);

        $unicodeRange = $unicodeRange !== null && preg_match('/^[uU]\+[0-9a-fA-F?\-]+(\s*,\s*[uU]\+[0-9a-fA-F?\-]+)*$/', trim($unicodeRange))
            ? trim($unicodeRange)
            : null;

        $file = sha1($contents).'.'.$extension;
        $this->files->ensureDirectoryExists($this->directory());

        if (! $this->files->exists($this->directory().'/'.$file)) {
            $this->files->put($this->directory().'/'.$file, $contents);
        }

        $face = [
            'id' => 'site:'.$file,
            'family' => $family,
            'style' => $style === 'italic' ? 'italic' : 'normal',
            'weight' => $weight,
            'unicode_range' => $unicodeRange,
            'file' => $file,
            'source' => in_array($source, ['google', 'upload'], true) ? $source : 'upload',
        ];

        $saved = collect($this->saved())->reject(fn ($existing) => $existing['id'] === $face['id'])->push($face)->values()->all();
        $this->write($saved);

        return $face;
    }

    /**
     * Remove one of the site's families and its files.
     */
    public function delete(string $family): void
    {
        [$removed, $kept] = collect($this->saved())->partition(fn ($face) => $face['family'] === $family);

        $this->write($kept->values()->all());

        $removed->each(function ($face) use ($kept) {
            if (! $kept->contains('file', $face['file'])) {
                $this->files->delete($this->directory().'/'.$face['file']);
            }
        });
    }

    /**
     * A family's faces with their files inlined, for exporting a template.
     */
    public function export(string $family): array
    {
        return collect($this->saved())
            ->where('family', $family)
            ->map(fn ($face) => [
                'family' => $face['family'],
                'style' => $face['style'],
                'weight' => $face['weight'],
                'unicode_range' => $face['unicode_range'],
                'source' => $face['source'],
                'data' => $this->dataUri($face),
            ])
            ->values()
            ->all();
    }

    /**
     * Add exported faces (as export() makes them) the site doesn't have.
     *
     * @throws InvalidArgumentException
     */
    public function import(array $faces): void
    {
        foreach ($faces as $face) {
            if (! is_array($face) || ! is_string($face['data'] ?? null)) {
                continue;
            }

            if (! preg_match('#^data:font/(woff2|woff|ttf|otf);base64,([A-Za-z0-9+/=]+)$#', $face['data'], $matches)) {
                throw new InvalidArgumentException(__('A font in this template isn’t a WOFF2, WOFF, TTF or OTF file.'));
            }

            $family = Schema::family($face['family'] ?? null);

            if (! $family || $this->isBundled($family)) {
                continue;
            }

            $this->add(
                $family,
                base64_decode($matches[2]),
                $matches[1],
                is_array($face['weight'] ?? null) ? $face['weight'] : (int) ($face['weight'] ?? 400),
                ($face['style'] ?? 'normal') === 'italic' ? 'italic' : 'normal',
                is_string($face['unicode_range'] ?? null) ? $face['unicode_range'] : null,
                ($face['source'] ?? null) === 'google' ? 'google' : 'upload',
            );
        }
    }

    protected function absolute(array $face): string
    {
        return $face['source'] === 'bundled'
            ? __DIR__.'/../../resources/fonts/'.$face['file']
            : $this->directory().'/'.$face['file'];
    }

    protected function bundled(): array
    {
        if ($this->bundled !== null) {
            return $this->bundled;
        }

        $manifest = json_decode((string) file_get_contents(__DIR__.'/../../resources/fonts/fonts.json'), true) ?: [];

        return $this->bundled = collect($manifest)->flatMap(fn ($family) => collect($family['files'])->map(fn ($file) => [
            'id' => 'bundled:'.$file['file'],
            'family' => $family['family'],
            'category' => $family['category'],
            'style' => $file['style'],
            'weight' => $file['weight'],
            'unicode_range' => $file['unicode_range'],
            'file' => $file['file'],
            'source' => 'bundled',
        ]))->all();
    }

    protected function saved(): array
    {
        if ($this->saved !== null) {
            return $this->saved;
        }

        $path = $this->directory().'/fonts.json';
        $faces = $this->files->exists($path) ? json_decode($this->files->get($path), true) : [];

        return $this->saved = collect(is_array($faces) ? $faces : [])
            ->filter(fn ($face) => is_array($face) && preg_match('/^[a-f0-9]{40}\.(woff2|woff|ttf|otf)$/', $face['file'] ?? ''))
            ->map(fn ($face) => [...$face, 'category' => 'sans-serif'])
            ->values()
            ->all();
    }

    protected function write(array $faces): void
    {
        $this->files->ensureDirectoryExists($this->directory());

        $faces = collect($faces)->map(fn ($face) => collect($face)->except('category')->all())->values()->all();

        $this->files->put($this->directory().'/fonts.json', json_encode($faces, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->saved = null;
    }
}
