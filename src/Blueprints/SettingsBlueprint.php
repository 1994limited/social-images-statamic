<?php

namespace NineteenNinetyFour\SocialImages\Blueprints;

use NineteenNinetyFour\SocialImages\Integrations\SeoPro;
use NineteenNinetyFour\SocialImages\Settings;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Site;

/**
 * The settings page (Tools → Addons → Social Images → Settings).
 */
class SettingsBlueprint
{
    public function contents(): array
    {
        $defaults = Settings::defaults();

        $extraLayouts = collect(Schema::PRESETS)->except(Schema::BASE_LAYOUT)
            ->map(fn ($preset) => __($preset['title']).' ('.$preset['width'].' × '.$preset['height'].')')
            ->all();

        $ruleFields = [
            $this->field('collections', ['type' => 'collections', 'display' => __('Collections'), 'instructions' => __('Leave empty for every collection.'), 'mode' => 'select', 'width' => 50]),
            $this->field('template', ['type' => 'social_image_template', 'display' => __('Template'), 'hide_none' => true, 'validate' => ['required'], 'width' => 50]),
            $this->field('layouts', ['type' => 'checkboxes', 'display' => __('Also make'), 'instructions' => __('Sizes made on save beside Open Graph, if the template has them. Others are made when a page asks for them.'), 'options' => $extraLayouts, 'inline' => true]),
        ];

        if (Site::multiEnabled()) {
            array_splice($ruleFields, 1, 0, [$this->field('sites', ['type' => 'sites', 'display' => __('Sites'), 'instructions' => __('Leave empty for every site.'), 'mode' => 'select', 'width' => 50])]);
        }

        $sections = [[
            'display' => __('Templates'),
            'instructions' => __('Which template entries use. The first rule that matches an entry is used; an entry can also choose its own in its Social image fields.'),
            'fields' => [
                $this->field('templates', [
                    'type' => 'grid',
                    'display' => __('Rules'),
                    'hide_display' => true,
                    'full_width_setting' => true,
                    'mode' => 'stacked',
                    'add_row' => __('Add rule'),
                    'fields' => $ruleFields,
                ]),
            ],
        ], [
            'display' => __('Generating'),
            'fields' => [
                $this->field('generate_on_save', ['type' => 'toggle', 'display' => __('Generate when an entry is saved'), 'instructions' => __('Otherwise images are made when a page first asks for one, or from Tools → Social Images → Images.'), 'default' => $defaults['generate_on_save'], 'width' => 50]),
                ...($container = AssetContainer::all()->first()) ? [$this->field('fallback_image', [
                    'type' => 'assets',
                    'display' => __('Fallback image'),
                    'instructions' => __('Shared while an entry’s image is being made, and for pages without one.'),
                    'max_files' => 1,
                    'mode' => 'list',
                    'container' => $container->handle(),
                    'width' => 50,
                ])] : [],
            ],
        ]];

        $sections[] = [
            'display' => 'SEO Pro',
            'instructions' => SeoPro::installed() ? null : __('SEO Pro isn’t installed. Without it, add {{ social_image:meta }} to your layout’s <head>.'),
            'fields' => [
                $this->field('seo_pro', [
                    'type' => 'button_group',
                    'display' => __('Use the social image for og:image and twitter:image'),
                    'options' => [
                        'replace' => __('Unless the entry has its own SEO image'),
                        'always' => __('Always'),
                        'off' => __('Never'),
                    ],
                    'default' => $defaults['seo_pro'],
                ]),
            ],
        ];

        return ['tabs' => ['main' => ['display' => __('Settings'), 'sections' => $sections]]];
    }

    protected function field(string $handle, array $config): array
    {
        return ['handle' => $handle, 'field' => $config];
    }
}
