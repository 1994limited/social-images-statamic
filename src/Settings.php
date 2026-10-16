<?php

namespace NineteenNinetyFour\SocialImages;

use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Facades\Addon;
use Throwable;

/**
 * The settings edited in the Control Panel (Tools → Addons → Social Images
 * → Settings): which template each collection uses, which extra sizes are
 * made, the fallback image and SEO Pro. Read as saved, with the defaults
 * below filling any gaps.
 */
class Settings
{
    public const PACKAGE = '1994/social-images-statamic';

    protected ?array $saved = null;

    public static function defaults(): array
    {
        return [
            'templates' => [],
            'generate_on_save' => true,
            'fallback_image' => null,
            'seo_pro' => 'replace',
        ];
    }

    public function saved(): array
    {
        if ($this->saved !== null) {
            return $this->saved;
        }

        try {
            $addon = Addon::get(self::PACKAGE);
            $saved = $addon ? app(SettingsRepository::class)->find($addon->id())?->raw() : null;
        } catch (Throwable) {
            $saved = null;
        }

        return $this->saved = is_array($saved) ? $saved : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->saved()[$key] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return $default ?? static::defaults()[$key] ?? null;
        }

        return $value;
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key);

        return is_bool($value) ? $value : (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The template rules, in order. Each says which template, and which
     * extra layouts, entries in some collections and sites get; empty
     * collections or sites mean all of them.
     *
     * @return array<int, array{collections: array<int, string>, sites: array<int, string>, template: string, layouts: array<int, string>}>
     */
    public function rules(): array
    {
        return collect($this->get('templates'))
            ->filter(fn ($rule) => is_array($rule) && ! empty($rule['template']))
            ->map(fn ($rule) => [
                'collections' => array_values(array_filter((array) ($rule['collections'] ?? []))),
                'sites' => array_values(array_filter((array) ($rule['sites'] ?? []))),
                'template' => (string) $rule['template'],
                'layouts' => array_values(array_filter((array) ($rule['layouts'] ?? []))),
            ])
            ->values()
            ->all();
    }

    public function refresh(): static
    {
        $this->saved = null;

        return $this;
    }
}
