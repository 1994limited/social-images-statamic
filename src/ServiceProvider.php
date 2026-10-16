<?php

namespace NineteenNinetyFour\SocialImages;

use NineteenNinetyFour\SocialImages\Actions\GenerateSocialImages;
use NineteenNinetyFour\SocialImages\Blueprints\SettingsBlueprint;
use NineteenNinetyFour\SocialImages\Commands\Generate;
use NineteenNinetyFour\SocialImages\Fieldtypes\SocialImagePreview;
use NineteenNinetyFour\SocialImages\Fieldtypes\SocialImageTemplate;
use NineteenNinetyFour\SocialImages\Images\Camera;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Images\Output;
use NineteenNinetyFour\SocialImages\Integrations\SeoPro;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\Library\Uploads;
use NineteenNinetyFour\SocialImages\Listeners\ForgetOnDelete;
use NineteenNinetyFour\SocialImages\Listeners\GenerateOnSave;
use NineteenNinetyFour\SocialImages\Rendering\Page;
use NineteenNinetyFour\SocialImages\Tags\SocialImageTags;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use Statamic\Events\AddonSettingsSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /** The Control Panel nav icon (resources/brand/nav-icon.svg). */
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path d="M1.5 3H9.25V5.75H12.5V11H1.5Z" stroke="currentColor" stroke-width="1" stroke-linejoin="round"></path><path d="M10.25 3.25L12.25 5.25" stroke="currentColor" stroke-width="1" stroke-linecap="round"></path><path d="M3 9.5L5.5 6.75L7.4 8.75L8.75 7.5L11 9.5" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="4.2" cy="5.1" r=".75" stroke="currentColor" stroke-width="1"></circle></svg>';

    protected $tags = [
        SocialImageTags::class,
    ];

    protected $fieldtypes = [
        SocialImagePreview::class,
        SocialImageTemplate::class,
    ];

    protected $actions = [
        GenerateSocialImages::class,
    ];

    protected $commands = [
        Generate::class,
    ];

    protected $listen = [
        EntrySaved::class => [GenerateOnSave::class],
        EntryDeleted::class => [ForgetOnDelete::class],
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    /**
     * The package is 1994/social-images-statamic; its config, views and
     * fieldsets are read as plain "social-images" instead.
     */
    protected $config = false;

    protected $translations = false;

    protected $fieldsetNamespace = 'social-images';

    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/social-images.php', 'social-images');

        $this->app->singleton(Settings::class);
        $this->app->singleton(TemplateRepository::class);
        $this->app->singleton(Fonts::class);
        $this->app->singleton(Uploads::class);
        $this->app->singleton(Output::class);
        $this->app->singleton(Page::class);
        $this->app->singleton(Cards::class);
        $this->app->bind(Camera::class);
    }

    public function bootAddon(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'social-images');

        $this->publishes([__DIR__.'/../config/social-images.php' => config_path('social-images.php')], 'social-images-config');

        $this->registerSettingsBlueprint(fn () => app(SettingsBlueprint::class)->contents());

        SeoPro::register();

        $this->app['events']->listen(AddonSettingsSaved::class, function (AddonSettingsSaved $event) {
            if ($event->settings->addon()->package() === Settings::PACKAGE) {
                app(Settings::class)->refresh();
            }
        });

        Permission::extend(function () {
            Permission::group('social-images', 'Social Images', function () {
                Permission::register('view social images', function ($permission) {
                    $permission->label(__('View social images and templates'))->children([
                        Permission::make('manage social image templates')->label(__('Design, import, export and delete templates, and generate any entry’s image')),
                    ]);
                });
            });
        });

        Nav::extend(function ($nav) {
            $nav->tools('Social Images')
                ->route('social-images.index')
                ->icon(self::ICON)
                ->can('view social images')
                ->children([
                    $nav->item(__('Templates'))->route('social-images.index'),
                    $nav->item(__('Images'))->route('social-images.images.index'),
                ]);
        });
    }
}
