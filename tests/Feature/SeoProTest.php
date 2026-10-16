<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Integrations\SeoPro;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Addons\Manifest;
use Statamic\Facades\Antlers;
use Statamic\Facades\Entry;
use Statamic\SeoPro\ServiceProvider;

class SeoProTest extends TestCase
{
    /**
     * SEO Pro is booted as an addon too, so its tags run as on a site.
     */
    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), ServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $manifest = $app->make(Manifest::class);
        $manifest->manifest['statamic/seo-pro'] = [
            'id' => 'statamic/seo-pro',
            'slug' => 'seo-pro',
            'version' => '7.15.1',
            'namespace' => 'Statamic\\SeoPro',
            'autoload' => 'src',
            'provider' => ServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
    }

    protected function generated(array $data = []): \Statamic\Contracts\Entries\Entry
    {
        $entry = $this->entry(data: $data);
        Storage::disk('public')->put(app(Cards::class)->for($entry)->path(), 'png');

        return $entry;
    }

    public function test_seo_pro_is_installed_in_the_test_suite(): void
    {
        $this->assertTrue(SeoPro::installed());
    }

    public function test_the_generated_image_replaces_seo_pros_and_glide_is_switched_off(): void
    {
        $entry = $this->generated();

        $meta = app(SeoPro::class)->apply(['image' => 'section-default'], $entry);

        $this->assertStringEndsWith(app(Cards::class)->for($entry)->path(), $meta['image']['permalink']);
        $this->assertSame(1200, $meta['image']['width']);
        $this->assertFalse($meta['is_og_glide_enabled']);
        $this->assertFalse($meta['is_twitter_glide_enabled']);
    }

    public function test_an_entrys_own_seo_image_wins_unless_always(): void
    {
        $entry = $this->generated(['seo' => ['image' => 'mine.jpg']]);

        $this->assertSame(['image' => 'x'], app(SeoPro::class)->apply(['image' => 'x'], $entry));

        $this->settings(['templates' => [['template' => 'basic']], 'seo_pro' => 'always']);
        $this->assertIsArray(app(SeoPro::class)->apply(['image' => 'x'], Entry::find('one'))['image']);
    }

    public function test_a_reference_to_another_field_is_not_the_entrys_own_image(): void
    {
        $entry = $this->generated(['seo' => ['image' => '@seo:featured_image']]);

        $this->assertIsArray(app(SeoPro::class)->apply(['image' => 'x'], $entry)['image']);
    }

    public function test_off_leaves_seo_pro_alone(): void
    {
        $entry = $this->generated();
        $this->settings(['templates' => [['template' => 'basic']], 'seo_pro' => 'off']);

        $this->assertSame(['image' => 'x'], app(SeoPro::class)->apply(['image' => 'x'], $entry));
    }

    public function test_while_the_image_is_being_made_seo_pros_own_stays(): void
    {
        $entry = $this->entry();

        $this->assertSame(['image' => 'x'], app(SeoPro::class)->apply(['image' => 'x'], $entry));
    }

    public function test_seo_pros_meta_tag_outputs_the_image(): void
    {
        $entry = $this->generated();

        $html = (string) Antlers::parse('{{ seo_pro:meta }}', ['id' => 'one', ...$entry->toAugmentedArray()], true);

        $this->assertStringContainsString('<meta property="og:image" content="'.app(Cards::class)->for($entry)->url().'"', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="'.app(Cards::class)->for($entry)->url().'"', $html);
    }
}
