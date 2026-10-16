<?php

namespace NineteenNinetyFour\SocialImages\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use NineteenNinetyFour\SocialImages\Images\Camera;
use NineteenNinetyFour\SocialImages\ServiceProvider;
use NineteenNinetyFour\SocialImages\Settings;
use NineteenNinetyFour\SocialImages\Templates\JsonTemplate;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use Statamic\Contracts\Addons\SettingsRepository;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Addon;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles, PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected FakeCamera $camera;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('social-images.path', self::libraryPath());
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('view.paths', [__DIR__.'/__fixtures__/views']);
    }

    public static function libraryPath(): string
    {
        return __DIR__.'/__fixtures__/dev-null/social-images';
    }

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(self::libraryPath());
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory(self::libraryPath()));

        $this->clearSettings();
        $this->beforeApplicationDestroyed(fn () => $this->clearSettings());

        // Blueprints and users are saved to the test app's resources.
        File::deleteDirectory(resource_path('blueprints'));
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory(resource_path('blueprints')));

        Storage::fake('public');

        $this->camera = new FakeCamera;
        $this->app->instance(Camera::class, $this->camera);

        Collection::make('pages')->routes('/{slug}')->save();
        Collection::make('articles')->routes('/articles/{slug}')->dated(true)->save();
    }

    /**
     * A designer template with a title text layer, saved.
     */
    protected function template(string $handle = 'basic', array $layers = [], array $extra = []): JsonTemplate
    {
        return app(TemplateRepository::class)->save($handle, [
            'schema' => 'social-images/1',
            'title' => ucfirst($handle),
            'layouts' => [
                'og' => [
                    'width' => 1200,
                    'height' => 630,
                    'background' => ['type' => 'solid', 'color' => '#ffffff'],
                    'layers' => $layers ?: [
                        ['id' => 'title', 'type' => 'text', 'text' => '{title}', 'x' => 80, 'y' => 80, 'width' => 1040, 'height' => 400, 'size' => 72],
                    ],
                ],
                ...$extra,
            ],
        ]);
    }

    protected function entry(string $id = 'one', string $title = 'Hello world', string $collection = 'articles', array $data = []): EntryContract
    {
        $entry = Entry::make()->collection($collection)->id($id)->slug(str($title)->slug())->data(['title' => $title, ...$data]);

        if ($collection === 'articles') {
            $entry->date('2026-10-16');
        }

        $entry->save();

        return $entry;
    }

    /**
     * An image asset in a faked "assets" container.
     */
    protected function asset(string $path = 'photo.png', int $width = 800, int $height = 600, array $data = []): Asset
    {
        if (! AssetContainer::find('assets')) {
            Storage::fake('assets', ['url' => '/assets']);
            AssetContainer::make('assets')->disk('assets')->save();
        }

        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 80, 40));
        ob_start();
        imagepng($image);
        Storage::disk('assets')->put($path, ob_get_clean());

        $asset = AssetContainer::find('assets')->makeAsset($path)->data($data);
        $asset->save();

        return $asset;
    }

    protected function useTemplate(string $handle, array $rule = []): void
    {
        $this->settings(['templates' => [['template' => $handle, ...$rule]]]);
    }

    protected function cpUser(array $permissions = ['view social images', 'manage social image templates']): \Statamic\Contracts\Auth\User
    {
        $this->setTestRoles(['tester' => ['access cp', ...$permissions]]);

        $user = User::make()->email('ada@example.com')->id('ada');
        $user->assignRole('tester');
        $user->save();

        return $user;
    }

    protected function clearSettings(): void
    {
        if ($addon = Addon::get(Settings::PACKAGE)) {
            app(SettingsRepository::class)->find($addon->id())?->delete();
        }

        app(Settings::class)->refresh();
    }

    protected function settings(array $values): void
    {
        $addon = Addon::get(Settings::PACKAGE);
        app(SettingsRepository::class)->make($addon, $values)->save();
        app(Settings::class)->refresh();
    }
}
