<?php

namespace NineteenNinetyFour\SocialImages\Images;

use Illuminate\Support\Facades\Cache;
use NineteenNinetyFour\SocialImages\Templates\Template;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as Entries;

/**
 * One entry's image in one layout, and the PNG file it's kept in.
 *
 * The file name carries a fingerprint of everything that changes the
 * picture (see Template::fingerprint()), so an unchanged entry maps to a
 * file that already exists and a changed one to a file that doesn't. That
 * is the whole "does this need making" check.
 */
class Card
{
    protected ?string $hash = null;

    public function __construct(
        public readonly Entry $entry,
        public readonly Template $template,
        public readonly string $layout,
    ) {}

    public function hash(): string
    {
        return $this->hash ??= $this->template->fingerprint($this->entry, $this->layout);
    }

    /**
     * The folder this entry's images are in, e.g. social-images/{entry id}.
     */
    public function folder(): string
    {
        return $this->output()->directory().'/'.$this->entry->id();
    }

    /**
     * e.g. social-images/{entry id}/og-{hash}.png
     */
    public function path(): string
    {
        return $this->folder().'/'.$this->layout.'-'.$this->hash().'.png';
    }

    public function width(): int
    {
        return $this->template->layouts()[$this->layout]['width'];
    }

    public function height(): int
    {
        return $this->template->layouts()[$this->layout]['height'];
    }

    public function exists(): bool
    {
        return $this->output()->exists($this->path());
    }

    public function url(): string
    {
        return $this->output()->url($this->path());
    }

    /**
     * A root-relative URL with the file's time appended, for the Control
     * Panel. A forced re-render keeps the file name, so without the time
     * the browser would keep showing its cached copy.
     */
    public function previewUrl(): string
    {
        return (parse_url($this->url(), PHP_URL_PATH) ?: $this->url()).'?v='.$this->output()->lastModified($this->path());
    }

    /**
     * This layout's files on disk, current or stale.
     *
     * @return array<int, string>
     */
    public function files(): array
    {
        $prefix = $this->folder().'/'.$this->layout.'-';

        return collect($this->output()->files($this->folder()))
            ->filter(fn (string $file) => str_starts_with($file, $prefix) && str_ends_with($file, '.png'))
            ->values()
            ->all();
    }

    /**
     * Render the image, save it and delete this layout's older files.
     */
    public function render(): string
    {
        $size = $this->template->layouts()[$this->layout];

        $png = app(Camera::class)->capture($this->template->html($this->entry, $this->layout), $size['width'], $size['height']);

        // One render at a time replaces this layout's files, and none
        // deletes the image that's current now: a slow render of an older
        // version can finish after a newer one.
        Cache::lock('social-images:write:'.$this->entry->id().':'.$this->layout, 30)->block(10, function () use ($png) {
            $this->output()->put($this->path(), $png);

            $current = $this->currentPath();

            collect($this->files())
                ->reject(fn (string $file) => $file === $this->path() || $file === $current)
                ->each(fn (string $file) => $this->output()->delete($file));
        });

        return $this->path();
    }

    /**
     * The path the entry's image has now, as saved, which may have moved
     * on since this card was made.
     */
    protected function currentPath(): ?string
    {
        $entry = Entries::find($this->entry->id());

        // Through Cards, so a change of template since this render began
        // counts too.
        return $entry ? rescue(fn () => app(Cards::class)->for($entry, $this->layout)?->path(), null, false) : null;
    }

    /**
     * The newest earlier image for this layout, if there is one: what to
     * show while the current one is being made.
     */
    public function previous(): ?string
    {
        return collect($this->files())
            ->reject(fn (string $file) => $file === $this->path())
            ->sortByDesc(fn (string $file) => $this->output()->lastModified($file))
            ->first();
    }

    protected function output(): Output
    {
        return app(Output::class);
    }
}
