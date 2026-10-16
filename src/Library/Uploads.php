<?php

namespace NineteenNinetyFour\SocialImages\Library;

use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * Images uploaded to templates in the designer: logos, textures, photos.
 *
 * Each is stored once, named by the SHA-1 of its contents, in the uploads
 * folder beside the templates. A template refers to one as
 * "upload:{sha1}.{ext}", so the same file used by two templates (or
 * imported twice) is one file.
 */
class Uploads
{
    public const TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'avif' => 'image/avif',
    ];

    /** The largest file accepted, in bytes. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(protected Filesystem $files) {}

    public function directory(): string
    {
        return rtrim((string) config('social-images.path'), '/').'/uploads';
    }

    /**
     * Store an image and return its name, e.g. "3f…e1.png".
     *
     * @throws InvalidArgumentException when it isn't an image we take
     */
    public function put(string $contents, string $extension): string
    {
        $extension = strtolower($extension) === 'jpeg' ? 'jpg' : strtolower($extension);

        if (! isset(self::TYPES[$extension])) {
            throw new InvalidArgumentException(__('Images must be PNG, JPEG, WebP, GIF, SVG or AVIF.'));
        }

        if (strlen($contents) > self::MAX_BYTES) {
            throw new InvalidArgumentException(__('Images must be smaller than 5 MB.'));
        }

        if (! $this->looksLike($contents, $extension)) {
            throw new InvalidArgumentException(__('That file isn’t the kind of image its name says it is.'));
        }

        $name = sha1($contents).'.'.$extension;

        if (! $this->exists($name)) {
            $this->files->ensureDirectoryExists($this->directory());
            $this->files->put($this->path($name), $contents);
        }

        return $name;
    }

    public function exists(string $name): bool
    {
        return $this->valid($name) && $this->files->exists($this->path($name));
    }

    public function get(string $name): ?string
    {
        return $this->exists($name) ? $this->files->get($this->path($name)) : null;
    }

    public function mime(string $name): string
    {
        return self::TYPES[pathinfo($name, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    }

    public function dataUri(string $name): ?string
    {
        $contents = $this->get($name);

        return $contents === null ? null : 'data:'.$this->mime($name).';base64,'.base64_encode($contents);
    }

    public function path(string $name): string
    {
        if (! $this->valid($name)) {
            throw new InvalidArgumentException("Invalid upload name [{$name}].");
        }

        return $this->directory().'/'.$name;
    }

    public function valid(string $name): bool
    {
        return (bool) preg_match('/^[a-f0-9]{40}\.(png|jpg|webp|gif|svg|avif)$/', $name);
    }

    /**
     * A quick check of the file's opening bytes against its extension, so a
     * renamed file of another kind is turned away.
     */
    protected function looksLike(string $contents, string $extension): bool
    {
        return match ($extension) {
            'png' => str_starts_with($contents, "\x89PNG"),
            'jpg' => str_starts_with($contents, "\xFF\xD8"),
            'gif' => str_starts_with($contents, 'GIF8'),
            'webp' => substr($contents, 0, 4) === 'RIFF' && substr($contents, 8, 4) === 'WEBP',
            'avif' => substr($contents, 4, 8) === 'ftypavif' || substr($contents, 4, 8) === 'ftypavis',
            'svg' => (bool) preg_match('/<svg[\s>]/i', substr($contents, 0, 4096)),
            default => false,
        };
    }
}
