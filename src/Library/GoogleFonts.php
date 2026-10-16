<?php

namespace NineteenNinetyFour\SocialImages\Library;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use Throwable;

/**
 * Copies a family from Google Fonts into the site's fonts, once, when it's
 * added in the designer. Renders then use the copy and never call Google.
 *
 * Only the Latin and Latin Extended files are kept, which cover English and
 * most European languages and keep each image's page small.
 */
class GoogleFonts
{
    public const SUBSETS = ['latin', 'latin-ext'];

    /**
     * Google serves WOFF2 only to browsers it knows support it.
     */
    protected const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    public function __construct(protected Fonts $fonts) {}

    /**
     * @param  array<int, int>  $weights
     * @return int the number of files added
     *
     * @throws InvalidArgumentException when Google doesn't have it
     */
    public function add(string $family, array $weights = [400, 700], bool $italic = false): int
    {
        $family = Schema::family($family) ?? throw new InvalidArgumentException(__('Font names can use letters, numbers, spaces, dots, dashes and ampersands.'));

        $weights = collect($weights)->map(fn ($weight) => (int) $weight)
            ->filter(fn ($weight) => $weight >= 100 && $weight <= 900 && $weight % 100 === 0)
            ->unique()->sort()->values();

        if ($weights->isEmpty()) {
            $weights = collect([400]);
        }

        $axes = $weights->map(fn ($weight) => '0,'.$weight);

        if ($italic) {
            $axes = $axes->merge($weights->map(fn ($weight) => '1,'.$weight));
        }

        $url = 'https://fonts.googleapis.com/css2?family='.str_replace('%20', '+', rawurlencode($family)).':ital,wght@'.$axes->implode(';').'&display=block';

        try {
            $response = Http::withUserAgent(self::USER_AGENT)->timeout(15)->get($url);
        } catch (Throwable) {
            throw new InvalidArgumentException(__('Google Fonts couldn’t be reached. Try again in a moment.'));
        }

        if (! $response->successful()) {
            throw new InvalidArgumentException(__('Google Fonts doesn’t have :family in those weights.', ['family' => $family]));
        }

        $added = 0;

        foreach ($this->faces($response->body()) as $face) {
            if (! in_array($face['subset'], self::SUBSETS, true)) {
                continue;
            }

            try {
                $file = Http::withUserAgent(self::USER_AGENT)->timeout(15)->get($face['url']);
            } catch (Throwable) {
                throw new InvalidArgumentException(__('Google Fonts couldn’t be reached. Try again in a moment.'));
            }

            if (! $file->successful()) {
                continue;
            }

            $this->fonts->add($family, $file->body(), 'woff2', $face['weight'], $face['style'], $face['unicode_range'], 'google');
            $added++;
        }

        if ($added === 0) {
            throw new InvalidArgumentException(__('Google Fonts doesn’t have :family in those weights.', ['family' => $family]));
        }

        return $added;
    }

    /**
     * The @font-face blocks of Google's CSS, each with the subset named in
     * the comment before it. Only files on fonts.gstatic.com are taken.
     *
     * @return array<int, array{subset: string, style: string, weight: array{0: int, 1: int}, url: string, unicode_range: ?string}>
     */
    public function faces(string $css): array
    {
        preg_match_all('#/\*\s*([a-z0-9\-\[\]]+)\s*\*/\s*@font-face\s*\{([^}]*)\}#i', $css, $blocks, PREG_SET_ORDER);

        $faces = [];

        foreach ($blocks as [, $subset, $body]) {
            if (! preg_match('#src:\s*url\((https://fonts\.gstatic\.com/[^)\s]+\.woff2)\)#', $body, $url)) {
                continue;
            }

            preg_match('/font-weight:\s*(\d+)(?:\s+(\d+))?/', $body, $weight);
            preg_match('/font-style:\s*(\w+)/', $body, $style);
            preg_match('/unicode-range:\s*([^;]+);/', $body, $range);

            $faces[] = [
                'subset' => strtolower($subset),
                'style' => ($style[1] ?? 'normal') === 'italic' ? 'italic' : 'normal',
                'weight' => [(int) ($weight[1] ?? 400), (int) ($weight[2] ?? $weight[1] ?? 400)],
                'url' => $url[1],
                'unicode_range' => isset($range[1]) ? trim($range[1]) : null,
            ];
        }

        return $faces;
    }
}
