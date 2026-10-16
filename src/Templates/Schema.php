<?php

namespace NineteenNinetyFour\SocialImages\Templates;

use Illuminate\Support\Str;

/**
 * The shape of a designer template, and the one place it is checked.
 *
 * Everything that reaches the renderer, whether saved from the designer or
 * imported from a file or a URL, goes through normalize(). It builds a new
 * array from the keys it knows, coerces each value to its type and range,
 * and drops everything else. A template is therefore data only: positions,
 * colours, font names and field bindings. No HTML, CSS or script survives,
 * which is what makes importing a stranger's template safe.
 *
 * See docs/template-format.md.
 */
class Schema
{
    public const VERSION = 'social-images/1';

    public const MAX_LAYERS = 60;

    public const MAX_LAYOUTS = 8;

    public const MAX_TEXT = 500;

    /**
     * The layouts the designer offers, by handle. Others can be added with
     * any size between 200 and 2400 pixels each way.
     */
    public const PRESETS = [
        'og' => ['title' => 'Open Graph', 'width' => 1200, 'height' => 630],
        'square' => ['title' => 'Square', 'width' => 1080, 'height' => 1080],
        'portrait' => ['title' => 'Portrait', 'width' => 1080, 'height' => 1350],
        'pinterest' => ['title' => 'Pinterest', 'width' => 1000, 'height' => 1500],
        'story' => ['title' => 'Story', 'width' => 1080, 'height' => 1920],
    ];

    public const BASE_LAYOUT = 'og';

    /**
     * @throws InvalidTemplate
     */
    public static function normalize(array $data): array
    {
        $schema = $data['schema'] ?? self::VERSION;

        if ($schema !== self::VERSION) {
            throw new InvalidTemplate(__('This template is for a different version of Social Images (:schema).', ['schema' => is_string($schema) ? $schema : '?']));
        }

        $layouts = [];

        foreach (is_array($data['layouts'] ?? null) ? $data['layouts'] : [] as $handle => $layout) {
            if (count($layouts) >= self::MAX_LAYOUTS) {
                break;
            }

            $handle = self::handle($handle);

            if ($handle && is_array($layout)) {
                $layouts[$handle] = self::layout($layout, self::PRESETS[$handle] ?? null);
            }
        }

        if (! isset($layouts[self::BASE_LAYOUT])) {
            throw new InvalidTemplate(__('A template needs an Open Graph (og) layout.'));
        }

        // The base layout first, the others in the order given.
        $layouts = [self::BASE_LAYOUT => $layouts[self::BASE_LAYOUT]] + $layouts;

        return [
            'schema' => self::VERSION,
            'title' => self::string($data['title'] ?? null, 100) ?: __('Untitled template'),
            'description' => self::string($data['description'] ?? null, 300),
            'layouts' => $layouts,
        ];
    }

    public static function layout(array $layout, ?array $preset = null): array
    {
        $layers = [];
        $ids = [];

        foreach (array_slice(is_array($layout['layers'] ?? null) ? array_values($layout['layers']) : [], 0, self::MAX_LAYERS) as $layer) {
            if (! is_array($layer) || ! $layer = self::layer($layer)) {
                continue;
            }

            // Ids must be unique within a layout: they key the resolved data.
            while (isset($ids[$layer['id']])) {
                $layer['id'] = Str::lower(Str::random(8));
            }

            $ids[$layer['id']] = true;
            $layers[] = $layer;
        }

        return [
            'width' => self::int($layout['width'] ?? null, 200, 2400, $preset['width'] ?? 1200),
            'height' => self::int($layout['height'] ?? null, 200, 2400, $preset['height'] ?? 630),
            'background' => self::fill($layout['background'] ?? null) ?? ['type' => 'solid', 'color' => '#ffffff'],
            'layers' => $layers,
        ];
    }

    public static function layer(array $layer): ?array
    {
        $type = $layer['type'] ?? null;

        if (! in_array($type, ['text', 'image', 'shape'], true)) {
            return null;
        }

        $base = [
            'id' => self::id($layer['id'] ?? null),
            'type' => $type,
            'name' => self::string($layer['name'] ?? null, 60),
            'x' => self::number($layer['x'] ?? null, -2400, 4800, 0),
            'y' => self::number($layer['y'] ?? null, -2400, 4800, 0),
            'width' => self::number($layer['width'] ?? null, 1, 4800, 200),
            'height' => self::number($layer['height'] ?? null, 1, 4800, 100),
            'rotate' => self::number($layer['rotate'] ?? null, -360, 360, 0),
            'opacity' => self::number($layer['opacity'] ?? null, 0, 1, 1),
            'hidden' => (bool) ($layer['hidden'] ?? false),
            'locked' => (bool) ($layer['locked'] ?? false),
            'when' => self::expression($layer['when'] ?? null),
        ];

        return $base + match ($type) {
            'text' => self::text($layer),
            'image' => self::image($layer),
            'shape' => self::shape($layer),
        };
    }

    protected static function text(array $layer): array
    {
        $size = self::number($layer['size'] ?? null, 6, 400, 64);

        return [
            'text' => self::string($layer['text'] ?? null, self::MAX_TEXT, multiline: true),
            'font' => self::font($layer['font'] ?? null),
            'size' => $size,
            'min_size' => min($size, self::number($layer['min_size'] ?? null, 6, 400, $size)),
            'line_height' => self::number($layer['line_height'] ?? null, 0.6, 3, 1.15),
            'letter_spacing' => self::number($layer['letter_spacing'] ?? null, -0.2, 1, 0),
            'color' => self::color($layer['color'] ?? null) ?? '#111111',
            'align' => self::enum($layer['align'] ?? null, ['left', 'center', 'right'], 'left'),
            'valign' => self::enum($layer['valign'] ?? null, ['top', 'middle', 'bottom'], 'top'),
            'transform' => self::enum($layer['transform'] ?? null, ['none', 'uppercase', 'lowercase', 'capitalize'], 'none'),
            'max_lines' => self::int($layer['max_lines'] ?? null, 0, 20, 0),
            'balance' => (bool) ($layer['balance'] ?? true),
            'hug' => (bool) ($layer['hug'] ?? false),
            'background' => self::fill($layer['background'] ?? null),
            'padding' => self::padding($layer['padding'] ?? null),
            'radius' => self::number($layer['radius'] ?? null, 0, 2000, 0),
            'border' => self::border($layer['border'] ?? null),
            'shadow' => self::shadow($layer['shadow'] ?? null),
        ];
    }

    protected static function image(array $layer): array
    {
        return [
            'source' => self::sources($layer['source'] ?? null),
            'fit' => self::enum($layer['fit'] ?? null, ['cover', 'contain'], 'cover'),
            'radius' => self::number($layer['radius'] ?? null, 0, 2000, 0),
            'border' => self::border($layer['border'] ?? null),
            'shadow' => self::shadow($layer['shadow'] ?? null),
            'grayscale' => (bool) ($layer['grayscale'] ?? false),
            'background' => self::fill($layer['background'] ?? null),
        ];
    }

    protected static function shape(array $layer): array
    {
        return [
            'shape' => self::enum($layer['shape'] ?? null, ['rect', 'ellipse'], 'rect'),
            'fill' => self::fill($layer['fill'] ?? null),
            'radius' => self::number($layer['radius'] ?? null, 0, 2000, 0),
            'border' => self::border($layer['border'] ?? null),
            'shadow' => self::shadow($layer['shadow'] ?? null),
            'blur' => self::number($layer['blur'] ?? null, 0, 400, 0),
        ];
    }

    /**
     * A solid colour, or a linear or radial gradient of two to eight stops.
     */
    public static function fill(mixed $fill): ?array
    {
        if (is_string($fill)) {
            $fill = ['type' => 'solid', 'color' => $fill];
        }

        if (! is_array($fill)) {
            return null;
        }

        $type = $fill['type'] ?? 'solid';

        if ($type === 'solid') {
            $color = self::color($fill['color'] ?? null);

            return $color ? ['type' => 'solid', 'color' => $color] : null;
        }

        if (! in_array($type, ['linear', 'radial'], true)) {
            return null;
        }

        $stops = [];

        foreach (array_slice(is_array($fill['stops'] ?? null) ? array_values($fill['stops']) : [], 0, 8) as $stop) {
            $color = is_array($stop) ? self::color($stop['color'] ?? null) : null;

            if ($color) {
                $stops[] = ['color' => $color, 'position' => self::number($stop['position'] ?? null, 0, 100, 0)];
            }
        }

        if (count($stops) < 2) {
            return $stops ? ['type' => 'solid', 'color' => $stops[0]['color']] : null;
        }

        usort($stops, fn ($a, $b) => $a['position'] <=> $b['position']);

        return $type === 'linear'
            ? ['type' => 'linear', 'angle' => self::number($fill['angle'] ?? null, -360, 360, 180), 'stops' => $stops]
            : [
                'type' => 'radial',
                'x' => self::number($fill['x'] ?? null, 0, 100, 50),
                'y' => self::number($fill['y'] ?? null, 0, 100, 50),
                'stops' => $stops,
            ];
    }

    /**
     * #rgb, #rgba, #rrggbb or #rrggbbaa, lower-cased; "transparent" too.
     */
    public static function color(mixed $color): ?string
    {
        if (! is_string($color)) {
            return null;
        }

        $color = strtolower(trim($color));

        if ($color === 'transparent') {
            return '#00000000';
        }

        return preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color) ? $color : null;
    }

    public static function font(mixed $font): array
    {
        $font = is_array($font) ? $font : [];

        return [
            'family' => self::family($font['family'] ?? null) ?? 'Inter',
            'weight' => (int) (round(self::int($font['weight'] ?? null, 100, 900, 400) / 100) * 100),
            'style' => self::enum($font['style'] ?? null, ['normal', 'italic'], 'normal'),
        ];
    }

    /**
     * A font family name: letters, numbers, spaces and a little punctuation.
     */
    public static function family(mixed $family): ?string
    {
        if (! is_string($family)) {
            return null;
        }

        $family = trim(preg_replace('/\s+/', ' ', $family));

        return preg_match('/^[\pL\pN][\pL\pN \-_.&]{0,59}$/u', $family) ? $family : null;
    }

    /**
     * Where an image layer's picture comes from, tried in order until one
     * has an image: an entry field ("field:featured_image"), a site asset
     * ("asset:assets::logo.png"), or a file uploaded to the template
     * ("upload:3f…e1.png").
     *
     * @return array<int, string>
     */
    public static function sources(mixed $sources): array
    {
        $sources = is_string($sources) ? [$sources] : (is_array($sources) ? $sources : []);

        return collect($sources)
            ->filter(fn ($source) => is_string($source) && self::isSource($source))
            ->map(fn ($source) => trim($source))
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    public static function isSource(string $source): bool
    {
        $source = trim($source);

        return (bool) (preg_match('/^field:[a-z0-9_]{1,60}(\.[a-z0-9_]{1,60})?$/i', $source)
            || preg_match('/^upload:[a-f0-9]{40}\.(png|jpe?g|webp|gif|svg|avif)$/', $source)
            || preg_match('/^asset:[a-z0-9_\-]{1,60}::[^\x00-\x1f]{1,300}$/i', $source) && ! str_contains($source, '..'));
    }

    /**
     * A token expression, as used between braces in text. Checked loosely
     * here; Tokens decides what each part means.
     */
    public static function expression(mixed $expression): ?string
    {
        $expression = self::string($expression, 200);

        return $expression === '' ? null : $expression;
    }

    protected static function border(mixed $border): ?array
    {
        if (! is_array($border)) {
            return null;
        }

        $width = self::number($border['width'] ?? null, 0, 100, 0);
        $color = self::color($border['color'] ?? null);

        return $width > 0 && $color ? ['width' => $width, 'color' => $color] : null;
    }

    protected static function shadow(mixed $shadow): ?array
    {
        if (! is_array($shadow) || ! $color = self::color($shadow['color'] ?? null)) {
            return null;
        }

        return [
            'x' => self::number($shadow['x'] ?? null, -200, 200, 0),
            'y' => self::number($shadow['y'] ?? null, -200, 200, 10),
            'blur' => self::number($shadow['blur'] ?? null, 0, 400, 30),
            'color' => $color,
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float} top, right, bottom, left
     */
    protected static function padding(mixed $padding): array
    {
        if (is_numeric($padding)) {
            $padding = array_fill(0, 4, $padding);
        }

        $padding = is_array($padding) ? array_values($padding) : [];

        return [
            self::number($padding[0] ?? null, 0, 400, 0),
            self::number($padding[1] ?? $padding[0] ?? null, 0, 400, 0),
            self::number($padding[2] ?? $padding[0] ?? null, 0, 400, 0),
            self::number($padding[3] ?? $padding[1] ?? $padding[0] ?? null, 0, 400, 0),
        ];
    }

    public static function handle(mixed $handle): ?string
    {
        if (! is_string($handle) && ! is_int($handle)) {
            return null;
        }

        $handle = Str::slug((string) $handle, '_');

        return preg_match('/^[a-z][a-z0-9_]{0,39}$/', $handle) ? $handle : null;
    }

    protected static function id(mixed $id): string
    {
        return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{1,40}$/', $id) ? $id : Str::lower(Str::random(8));
    }

    protected static function string(mixed $value, int $max, bool $multiline = false): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = (string) $value;
        $value = $multiline
            ? preg_replace('/[^\P{C}\n]/u', '', str_replace("\r\n", "\n", $value))
            : preg_replace('/\p{C}/u', '', $value);

        return mb_substr(trim((string) $value), 0, $max);
    }

    protected static function number(mixed $value, float $min, float $max, float $default): float|int
    {
        $value = is_numeric($value) ? $value : $default;
        $value = round(max($min, min($max, (float) $value)), 3);

        return $value == (int) $value ? (int) $value : $value;
    }

    protected static function int(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) ? (int) max($min, min($max, round((float) $value))) : $default;
    }

    protected static function enum(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
