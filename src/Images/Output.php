<?php

namespace NineteenNinetyFour\SocialImages\Images;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Assets\AssetContainer;
use Statamic\Facades\AssetContainer as AssetContainers;

/**
 * Where generated images are kept: a disk and folder, or an asset
 * container (whose disk is used, and whose Assets area then lists them).
 */
class Output
{
    public function container(): ?AssetContainer
    {
        $handle = config('social-images.container');

        return $handle ? AssetContainers::find($handle) : null;
    }

    public function disk(): Filesystem
    {
        return $this->container()?->disk()->filesystem() ?? Storage::disk(config('social-images.disk', 'public'));
    }

    public function directory(): string
    {
        return trim((string) config('social-images.directory', 'social-images'), '/');
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function put(string $path, string $contents): void
    {
        $this->disk()->put($path, $contents, ['visibility' => 'public']);

        if ($container = $this->container()) {
            $container->makeAsset($path)->saveQuietly();
        }
    }

    public function delete(string $path): void
    {
        if ($container = $this->container()) {
            $container->asset($path)?->delete();
        }

        $this->disk()->delete($path);
    }

    public function url(string $path): string
    {
        $url = $this->container()?->url()
            ? rtrim($this->container()->url(), '/').'/'.ltrim($path, '/')
            : $this->disk()->url($path);

        return url($url);
    }

    public function lastModified(string $path): int
    {
        return (int) rescue(fn () => $this->disk()->lastModified($path), 0, false);
    }

    /**
     * @return array<int, string>
     */
    public function files(string $directory): array
    {
        return $this->disk()->files($directory);
    }

    public function deleteDirectory(string $directory): void
    {
        foreach ($this->files($directory) as $file) {
            $this->delete($file);
        }

        $this->disk()->deleteDirectory($directory);
    }
}
