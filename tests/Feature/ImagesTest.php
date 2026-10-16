<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class ImagesTest extends TestCase
{
    protected function cards(): Cards
    {
        return app(Cards::class);
    }

    public function test_the_file_is_named_for_the_entry_layout_and_fingerprint(): void
    {
        $this->template();
        $this->useTemplate('basic');

        $card = $this->cards()->for($this->entry());

        $this->assertMatchesRegularExpression('#^social-images/one/og-[a-f0-9]{12}\.png$#', $card->path());
        $this->assertStringEndsWith('/storage/'.$card->path(), $card->url());
        $this->assertSame([1200, 630], [$card->width(), $card->height()]);
    }

    public function test_the_fingerprint_changes_with_what_the_image_shows_and_nothing_else(): void
    {
        $this->template();
        $this->useTemplate('basic');

        $entry = $this->entry();
        $hash = $this->cards()->for($entry)->hash();

        $entry->set('unrelated', 'changed')->save();
        $this->assertSame($hash, $this->cards()->for(Entry::find('one'))->hash());

        $entry->set('title', 'Goodbye')->save();
        $this->assertNotSame($hash, $changed = $this->cards()->for(Entry::find('one'))->hash());

        // Editing the template changes every image made from it.
        $this->template(layers: [['id' => 'title', 'type' => 'text', 'text' => '{title}', 'size' => 60]]);
        $this->assertNotSame($changed, $this->cards()->for(Entry::find('one'))->hash());
    }

    public function test_replacing_a_picture_or_moving_its_focal_point_changes_the_fingerprint(): void
    {
        Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'featured_image', 'field' => ['type' => 'assets', 'container' => 'assets', 'max_files' => 1]],
        ]])->save();

        $asset = $this->asset();
        $this->template(layers: [['id' => 'pic', 'type' => 'image', 'source' => ['field:featured_image']]]);
        $this->useTemplate('basic');

        $hash = $this->cards()->for($this->entry(data: ['featured_image' => 'photo.png']))->hash();

        $asset->set('focus', '20-30-1')->save();

        $this->assertNotSame($hash, $this->cards()->for(Entry::find('one'))->hash());
    }

    public function test_rendering_saves_the_png_and_deletes_older_ones(): void
    {
        $this->template();
        $this->useTemplate('basic');

        Storage::disk('public')->put('social-images/one/og-000000000000.png', 'old');
        Storage::disk('public')->put('social-images/one/square-000000000000.png', 'other layout');

        $this->entry();
        $path = (new GenerateImage('one'))->handle();

        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing('social-images/one/og-000000000000.png');
        Storage::disk('public')->assertExists('social-images/one/square-000000000000.png');
        $this->assertSame([1200, 630], [$this->camera->captures[0]['width'], $this->camera->captures[0]['height']]);
    }

    public function test_an_existing_image_is_not_rendered_again_unless_forced(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->entry();
        $this->app->terminate();

        $this->assertCount(1, $this->camera->captures);

        (new GenerateImage('one'))->handle();
        $this->assertCount(1, $this->camera->captures);

        (new GenerateImage('one', force: true))->handle();
        $this->assertCount(2, $this->camera->captures);
    }

    public function test_saving_queues_an_image_only_when_it_changed(): void
    {
        $this->template();
        $this->useTemplate('basic');
        config(['queue.default' => 'redis']);
        Queue::fake();

        $entry = $this->entry();
        Queue::assertPushed(GenerateImage::class, fn ($job) => $job->entryId === 'one' && $job->layout === 'og');

        Storage::disk('public')->put($this->cards()->for($entry)->path(), 'png');
        Queue::fake();

        $entry->set('unrelated', 'x')->save();
        Queue::assertNotPushed(GenerateImage::class);
    }

    public function test_nothing_is_queued_when_disabled_or_generating_on_save_is_off(): void
    {
        $this->template();
        config(['queue.default' => 'redis']);
        Queue::fake();

        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry('a');

        $this->settings(['templates' => [['template' => 'basic']]]);
        config(['social-images.enabled' => false]);
        $this->entry('b');

        Queue::assertNotPushed(GenerateImage::class);
    }

    public function test_jobs_are_unique_per_entry_layout_and_fingerprint(): void
    {
        $this->assertSame('one:og:abc', (new GenerateImage('one', 'og', hash: 'abc'))->uniqueId());
    }

    public function test_on_the_sync_queue_a_render_waits_for_the_response_and_is_locked(): void
    {
        $this->template();
        $this->useTemplate('basic');

        $entry = $this->entry();
        $card = $this->cards()->for($entry);
        $lock = 'social-images:one:og:'.$card->hash();

        $this->assertCount(0, $this->camera->captures);
        $this->assertFalse(Cache::lock($lock, 1)->get(), 'The lock is held until the render runs.');

        $this->app->terminate();

        $this->assertCount(1, $this->camera->captures);
        $this->assertTrue(Cache::lock($lock, 1)->get(), 'The lock is released once it has.');
    }

    public function test_extra_layouts_are_made_when_the_rule_asks_and_the_template_has_them(): void
    {
        $this->template(extra: ['square' => ['layers' => []]]);
        $this->useTemplate('basic', ['layouts' => ['square', 'story']]);

        $entry = $this->entry();
        $this->app->terminate();

        $this->assertSame(['og', 'square'], $this->cards()->layoutsFor($entry));
        $this->assertSame([[1200, 630], [1080, 1080]], collect($this->camera->captures)->map(fn ($c) => [$c['width'], $c['height']])->all());
    }

    public function test_rules_pick_the_template_by_collection_and_site(): void
    {
        $this->template('articles');
        $this->template('everything');
        $this->settings(['templates' => [
            ['collections' => ['articles'], 'template' => 'articles'],
            ['template' => 'everything'],
        ]]);

        $this->assertSame('articles', $this->cards()->templateFor($this->entry('a'))->handle());
        $this->assertSame('everything', $this->cards()->templateFor($this->entry('b', collection: 'pages'))->handle());

        $this->settings(['templates' => [['sites' => ['fr'], 'template' => 'articles']]]);
        $this->assertNull($this->cards()->templateFor(Entry::find('a')));
    }

    public function test_an_entry_can_choose_another_template_or_none(): void
    {
        $this->template('basic');
        $this->template('special');
        $this->useTemplate('basic');

        $this->assertSame('special', $this->cards()->templateFor($this->entry('a', data: ['social_image_template' => 'special']))->handle());
        $this->assertNull($this->cards()->templateFor($this->entry('b', data: ['social_image_template' => 'none'])));
        $this->assertSame('basic', $this->cards()->templateFor($this->entry('c', data: ['social_image_template' => 'deleted']))->handle());
    }

    public function test_entries_without_a_url_get_no_image(): void
    {
        $this->template();
        $this->useTemplate('basic');
        Collection::make('snippets')->save();

        $this->assertNull($this->cards()->for($this->entry('s', collection: 'snippets')));
    }

    public function test_the_share_image_is_the_override_then_the_generated_image_then_the_fallback(): void
    {
        Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'social_image', 'field' => ['type' => 'assets', 'container' => 'assets', 'max_files' => 1]],
        ]])->save();

        $this->asset('fallback.png', 1200, 600);
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'fallback_image' => ['fallback.png']]);
        config(['queue.default' => 'redis']);
        Queue::fake();

        $entry = $this->entry();
        $image = $this->cards()->image($entry);
        $this->assertSame('fallback', $image['source']);
        $this->assertSame([1200, 600], [$image['width'], $image['height']]);
        Queue::assertPushed(GenerateImage::class);

        Storage::disk('public')->put($this->cards()->for($entry)->path(), 'png');
        $this->assertSame('generated', $this->cards()->image($entry)['source']);
        $this->assertSame('Hello world', $this->cards()->image($entry)['alt']);

        $this->asset('mine.png', 1200, 630);
        $entry->set('social_image', 'mine.png')->save();
        $this->assertSame('override', $this->cards()->image(Entry::find('one'))['source']);
        $this->assertStringEndsWith('/mine.png', $this->cards()->image(Entry::find('one'))['url']);
    }

    public function test_deleting_an_entry_deletes_its_images(): void
    {
        $this->template();
        $this->useTemplate('basic');

        $entry = $this->entry();
        $this->app->terminate();
        Storage::disk('public')->assertExists($this->cards()->for($entry)->path());

        $entry->delete();

        $this->assertSame([], Storage::disk('public')->allFiles('social-images/one'));
    }

    public function test_images_can_go_to_an_asset_container(): void
    {
        Storage::fake('social');
        AssetContainer::make('social')->disk('social')->save();
        config(['social-images.container' => 'social']);

        $this->template();
        $this->useTemplate('basic');

        $entry = $this->entry();
        $this->app->terminate();

        $path = $this->cards()->for($entry)->path();
        Storage::disk('social')->assertExists($path);
        $this->assertNotNull(Asset::find('social::'.$path));
    }
}
