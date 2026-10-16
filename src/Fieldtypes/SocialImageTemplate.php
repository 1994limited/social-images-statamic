<?php

namespace NineteenNinetyFour\SocialImages\Fieldtypes;

use NineteenNinetyFour\SocialImages\Templates\Template;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use Statamic\Fieldtypes\Select;

/**
 * A choice of template: the site's templates, plus "No image". Used on
 * entries (to pick one other than the collection's) and in the settings.
 */
class SocialImageTemplate extends Select
{
    protected static $handle = 'social_image_template';

    protected $component = 'select';

    protected $categories = ['special'];

    protected $selectable = false;

    public function config(?string $key = null, $fallback = null)
    {
        return match ($key) {
            'options' => $this->templateOptions(),
            'clearable' => true,
            default => parent::config($key, $fallback),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function templateOptions(): array
    {
        $options = app(TemplateRepository::class)->all()
            ->mapWithKeys(fn (Template $template) => [$template->handle() => $template->title()])
            ->all();

        if (! parent::config('hide_none')) {
            $options['none'] = __('No image');
        }

        return $options;
    }
}
