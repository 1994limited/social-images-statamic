<?php

namespace NineteenNinetyFour\SocialImages\Templates;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NineteenNinetyFour\SocialImages\Events\TemplateDeleted;
use NineteenNinetyFour\SocialImages\Events\TemplateSaved;

/**
 * Every template: the site's designer templates (JSON files in the
 * templates folder) and its code templates (the config's `views`).
 *
 * The starters that come with the addon (resources/templates) aren't
 * templates until one is added to the site, which copies it.
 */
class TemplateRepository
{
    /** @var array<string, Template>|null */
    protected ?array $cache = null;

    public function __construct(protected Filesystem $files) {}

    public function directory(): string
    {
        return rtrim((string) config('social-images.path'), '/').'/templates';
    }

    /**
     * @return Collection<string, Template>
     */
    public function all(): Collection
    {
        if ($this->cache !== null) {
            return collect($this->cache);
        }

        $templates = [];

        foreach (config('social-images.views', []) as $handle => $config) {
            if (($handle = Schema::handle($handle)) && is_array($config) && ! empty($config['view'])) {
                $templates[$handle] = new ViewTemplate($handle, $config);
            }
        }

        foreach ($this->files->glob($this->directory().'/*.json') ?: [] as $path) {
            $handle = Schema::handle(pathinfo($path, PATHINFO_FILENAME));

            if (! $handle || isset($templates[$handle])) {
                continue;
            }

            $data = json_decode((string) $this->files->get($path), true);

            try {
                $templates[$handle] = new JsonTemplate($handle, Schema::normalize(is_array($data) ? $data : []));
            } catch (InvalidTemplate $exception) {
                report($exception);
            }
        }

        uasort($templates, fn (Template $a, Template $b) => strcasecmp($a->title(), $b->title()));

        $this->cache = $templates;

        return collect($templates);
    }

    public function find(?string $handle): ?Template
    {
        return $handle ? $this->all()->get($handle) : null;
    }

    /**
     * Save a designer template, normalising it first.
     *
     * @throws InvalidTemplate
     */
    public function save(string $handle, array $data): JsonTemplate
    {
        $handle = Schema::handle($handle) ?? throw new InvalidTemplate(__('Template handles start with a letter and use letters, numbers and underscores.'));

        if ($this->find($handle) instanceof ViewTemplate) {
            throw new InvalidTemplate(__('A code template already uses the handle :handle.', ['handle' => $handle]));
        }

        $template = new JsonTemplate($handle, Schema::normalize($data));

        $this->files->ensureDirectoryExists($this->directory());
        $this->files->put($this->path($handle), json_encode($template->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->cache = null;

        TemplateSaved::dispatch($template);

        return $template;
    }

    public function delete(string $handle): void
    {
        $template = $this->find($handle);

        if (! $template instanceof JsonTemplate) {
            return;
        }

        $this->files->delete($this->path($handle));
        $this->cache = null;

        TemplateDeleted::dispatch($template);
    }

    /**
     * A handle not yet taken, from a title.
     */
    public function uniqueHandle(string $title): string
    {
        $base = Schema::handle(Str::slug($title, '_')) ?? 'template';
        $base = substr($base, 0, 32);
        $handle = $base;
        $n = 2;

        while ($this->find($handle) || $this->files->exists($this->path($handle))) {
            $handle = $base.'_'.$n++;
        }

        return $handle;
    }

    /**
     * The starters that come with the addon, as export files.
     *
     * @return array<string, array>
     */
    public function starters(): array
    {
        return collect($this->files->glob(__DIR__.'/../../resources/templates/*.json') ?: [])
            ->mapWithKeys(fn ($path) => [pathinfo($path, PATHINFO_FILENAME) => json_decode((string) $this->files->get($path), true)])
            ->filter(fn ($data) => is_array($data))
            ->all();
    }

    public function path(string $handle): string
    {
        return $this->directory().'/'.$handle.'.json';
    }

    public function refresh(): void
    {
        $this->cache = null;
    }
}
