<?php

namespace NineteenNinetyFour\SocialImages\Rendering;

use Illuminate\Support\Collection;
use NineteenNinetyFour\SocialImages\Library\Uploads;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\Glide;
use Statamic\Fields\Value;
use Statamic\Imaging\ImageGenerator;
use Throwable;

/**
 * Finds the picture for an image layer and inlines it.
 *
 * A layer lists its sources in order (see Schema::sources()); the first
 * that has an image wins. Site assets are cropped or fitted by Glide to the
 * exact size they're drawn at, using the asset's focal point, so the page
 * stays small however large the original is.
 */
class Images
{
    public function __construct(protected Uploads $uploads, protected Tokens $tokens) {}

    /**
     * The first source with an image: an Asset or an upload's name.
     */
    public function find(array $sources, ?Entry $entry): Asset|string|null
    {
        foreach ($sources as $source) {
            [$kind, $value] = explode(':', $source, 2);

            $found = match ($kind) {
                'field' => $entry ? $this->fromField($entry, $value) : null,
                'asset' => $this->asset($value),
                'upload' => $this->uploads->exists($value) ? $value : null,
                default => null,
            };

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * What identifies the picture, for fingerprints: an asset's path, when
     * it last changed and its focal point, or an upload's (content) name.
     */
    public function identity(Asset|string|null $image): string
    {
        if ($image instanceof Asset) {
            // Fields can hand back a copy held from earlier in the request.
            $image = AssetFacade::find($image->id()) ?? $image;

            return implode('|', [
                $image->id(),
                rescue(fn () => $image->lastModified()?->timestamp, 0, false),
                $image->get('focus'),
            ]);
        }

        return (string) $image;
    }

    /**
     * The picture as a data URI, sized for a box of this many pixels.
     */
    public function dataUri(Asset|string|null $image, int $width, int $height, string $fit = 'cover'): ?string
    {
        if ($image === null) {
            return null;
        }

        if (is_string($image)) {
            return $this->uploads->dataUri($image);
        }

        // Glide can't resize these; they're inlined as they are.
        if (in_array(strtolower((string) $image->extension()), ['svg', 'gif'], true) || ! $image->isImage()) {
            return $this->raw($image);
        }

        try {
            $path = app(ImageGenerator::class)->generateByAsset($image, [
                'w' => max(1, $width),
                'h' => max(1, $height),
                'fit' => $fit === 'contain' ? 'contain' : 'crop_focal',
                'fm' => 'webp',
                'q' => 90,
            ]);

            return 'data:image/webp;base64,'.base64_encode(Glide::cacheDisk()->get($path));
        } catch (Throwable $exception) {
            report($exception);

            return $this->raw($image);
        }
    }

    protected function raw(Asset $image): ?string
    {
        $contents = rescue(fn () => $image->disk()->get($image->path()), null, false);

        return $contents ? 'data:'.$image->mimeType().';base64,'.base64_encode($contents) : null;
    }

    /**
     * An asset field, or a field of the first thing a relationship field
     * points at ("author.avatar").
     */
    protected function fromField(Augmentable $item, string $handle): ?Asset
    {
        [$handle, $rest] = array_pad(explode('.', $handle, 2), 2, null);

        $value = rescue(fn () => $item->augmentedValue($handle), null, false);
        $value = $value instanceof Value ? rescue(fn () => $value->value(), null) : $value;

        if ($rest !== null) {
            $related = $this->first($value);

            return $related instanceof Augmentable ? $this->fromField($related, $rest) : null;
        }

        $first = $this->first($value);

        if ($first instanceof Asset) {
            return $first;
        }

        // A plain path, as some fieldtypes store it.
        return is_string($first) && $first !== '' ? $this->asset($first) : null;
    }

    protected function first(mixed $value): mixed
    {
        if ($value instanceof Collection || is_array($value)) {
            return collect($value)->first();
        }

        if (is_object($value) && method_exists($value, 'get') && method_exists($value, 'first')) {
            return $value->get()->first();
        }

        return $value;
    }

    protected function asset(string $id): ?Asset
    {
        if (str_contains($id, '..')) {
            return null;
        }

        $asset = str_contains($id, '::') ? AssetFacade::find($id) : null;

        return $asset instanceof Asset && $asset->isImage() || $asset?->extension() === 'svg' ? $asset : null;
    }
}
