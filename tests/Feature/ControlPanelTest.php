<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\Templates\Porter;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use NineteenNinetyFour\SocialImages\Tests\FakeCamera;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;

class ControlPanelTest extends TestCase
{
    public function test_the_templates_page_needs_permission(): void
    {
        $this->actingAs($this->cpUser([]))->getJson(cp_route('social-images.index'))->assertForbidden();

        $this->template();

        $this->actingAs($this->cpUser())->get(cp_route('social-images.index'))
            ->assertOk()
            ->assertSee('social-images::Templates', false)
            ->assertSee('Basic');
    }

    public function test_viewers_cannot_change_templates(): void
    {
        $this->template();
        $this->actingAs($this->cpUser(['view social images']));

        $this->postJson(cp_route('social-images.templates.store'), ['title' => 'X'])->assertForbidden();
        $this->getJson(cp_route('social-images.templates.edit', 'basic'))->assertForbidden();
        $this->patchJson(cp_route('social-images.templates.update', 'basic'), ['template' => []])->assertForbidden();
        $this->deleteJson(cp_route('social-images.templates.destroy', 'basic'))->assertForbidden();
        $this->postJson(cp_route('social-images.templates.import'), ['json' => '{}'])->assertForbidden();
        $this->postJson(cp_route('social-images.uploads.store'))->assertForbidden();
    }

    public function test_a_template_is_created_blank_or_from_a_starter(): void
    {
        $this->actingAs($this->cpUser());

        $this->postJson(cp_route('social-images.templates.store'), ['title' => 'My card'])
            ->assertOk()
            ->assertJson(['redirect' => cp_route('social-images.templates.edit', 'my_card')]);

        $starter = array_key_first(app(TemplateRepository::class)->starters());
        $this->postJson(cp_route('social-images.templates.store'), ['starter' => $starter])->assertOk();

        $this->assertCount(2, app(TemplateRepository::class)->all());
    }

    public function test_the_designer_opens_with_fonts_fields_and_entries(): void
    {
        $this->template();
        $this->entry();

        $this->actingAs($this->cpUser())->get(cp_route('social-images.templates.edit', 'basic'))
            ->assertOk()
            ->assertSee('social-images::Designer', false)
            ->assertSee('Fraunces')
            ->assertSee('Hello world');
    }

    public function test_saving_from_the_designer_normalises_the_template(): void
    {
        $this->template();

        $this->actingAs($this->cpUser())->patchJson(cp_route('social-images.templates.update', 'basic'), ['template' => [
            'schema' => 'social-images/1',
            'title' => 'Renamed',
            'layouts' => ['og' => ['layers' => [['type' => 'text', 'id' => 'x', 'text' => 'Hi', 'onclick' => 'evil()']]]],
        ]])->assertOk()->assertJsonPath('template.title', 'Renamed');

        $saved = json_decode(file_get_contents(app(TemplateRepository::class)->path('basic')), true);
        $this->assertArrayNotHasKey('onclick', $saved['layouts']['og']['layers'][0]);
    }

    public function test_an_invalid_template_is_not_saved(): void
    {
        $this->template();

        $this->actingAs($this->cpUser())->patchJson(cp_route('social-images.templates.update', 'basic'), ['template' => ['schema' => 'social-images/1', 'layouts' => []]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A template needs an Open Graph (og) layout.');
    }

    public function test_preview_resolves_an_unsaved_layout_for_an_entry(): void
    {
        $this->entry();

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.preview'), [
            'entry' => 'one',
            'layout' => ['layers' => [['type' => 'text', 'id' => 't', 'text' => '{title}!']]],
        ])->assertOk()->assertJsonPath('data.layers.t.text', 'Hello world!');
    }

    public function test_previewing_an_entry_needs_access_to_it(): void
    {
        $this->entry();

        $this->actingAs($this->cpUser(['view social images', 'view pages entries']))->postJson(cp_route('social-images.preview'), [
            'entry' => 'one',
            'layout' => ['layers' => [['type' => 'text', 'id' => 't', 'text' => '{secret}']]],
        ])->assertNotFound();
    }

    public function test_rendering_and_exporting_need_template_management(): void
    {
        $this->template();
        $this->actingAs($this->cpUser(['view social images']));

        $this->postJson(cp_route('social-images.render'), ['layout' => []])->assertForbidden();
        $this->getJson(cp_route('social-images.templates.export', 'basic'))->assertForbidden();
        $this->assertCount(0, $this->camera->captures);
    }

    public function test_render_returns_a_png_from_the_camera(): void
    {
        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.render'), [
            'layout' => ['width' => 1080, 'height' => 1080, 'layers' => []],
        ])->assertOk()->assertJsonPath('image', 'data:image/png;base64,'.base64_encode(FakeCamera::PNG));

        $this->assertSame(1080, $this->camera->captures[0]['width']);
    }

    public function test_render_explains_a_failure(): void
    {
        $this->camera->failWith('Chrome not found');

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.render'), ['layout' => []])
            ->assertStatus(500)
            ->assertJsonPath('message', 'The image couldn’t be rendered: Chrome not found');
    }

    public function test_thumbnails_are_locked_down(): void
    {
        $this->template();

        $response = $this->actingAs($this->cpUser())->get(cp_route('social-images.templates.thumbnail', 'basic'))->assertOk();

        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('sandbox allow-scripts', $response->headers->get('Content-Security-Policy'));
    }

    public function test_import_by_paste_file_and_url(): void
    {
        $json = json_encode(app(Porter::class)->export($this->template('brand')));
        $this->actingAs($this->cpUser());

        $this->postJson(cp_route('social-images.templates.import'), ['json' => $json])
            ->assertOk()->assertJson(['redirect' => cp_route('social-images.templates.edit', 'brand_2')]);

        $this->post(cp_route('social-images.templates.import'), ['file' => UploadedFile::fake()->createWithContent('t.json', $json)], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['redirect' => cp_route('social-images.templates.edit', 'brand_3')]);

        Http::fake(['1.1.1.1/*' => Http::response($json)]);
        $this->postJson(cp_route('social-images.templates.import'), ['url' => 'https://1.1.1.1/brand.json', 'title' => 'From afar'])
            ->assertOk()->assertJson(['redirect' => cp_route('social-images.templates.edit', 'from_afar')]);

        $this->postJson(cp_route('social-images.templates.import'), ['url' => 'https://127.0.0.1/x'])
            ->assertStatus(422)->assertJsonPath('message', 'Templates can only be imported from public addresses.');
    }

    public function test_export_downloads_the_json(): void
    {
        $this->template();

        $response = $this->actingAs($this->cpUser())->get(cp_route('social-images.templates.export', 'basic'))->assertOk();

        $this->assertStringContainsString('basic.social-image.json', $response->headers->get('Content-Disposition'));
        $this->assertSame('Basic', json_decode($response->streamedContent(), true)['title']);
    }

    public function test_duplicate_and_delete(): void
    {
        $this->template();
        $this->actingAs($this->cpUser());

        $this->postJson(cp_route('social-images.templates.duplicate', 'basic'))->assertOk();
        $this->assertNotNull(app(TemplateRepository::class)->find('basic_copy'));

        $this->deleteJson(cp_route('social-images.templates.destroy', 'basic'))->assertOk();
        $this->assertNull(app(TemplateRepository::class)->find('basic'));
    }

    public function test_uploads_are_checked_and_served_sandboxed(): void
    {
        $this->actingAs($this->cpUser());

        $this->post(cp_route('social-images.uploads.store'), ['file' => UploadedFile::fake()->createWithContent('x.png', 'not a png')], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $source = $this->post(cp_route('social-images.uploads.store'), ['file' => UploadedFile::fake()->createWithContent('logo.svg', $svg)], ['Accept' => 'application/json'])
            ->assertOk()->json('source');

        $response = $this->get(cp_route('social-images.uploads.show', substr($source, 7)))->assertOk();
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_google_fonts_are_copied_once(): void
    {
        Http::fake([
            'fonts.googleapis.com/*' => Http::response(<<<'CSS'
                /* cyrillic */
                @font-face { font-family: 'Lora'; font-style: normal; font-weight: 400; src: url(https://fonts.gstatic.com/s/lora/cyr.woff2) format('woff2'); unicode-range: U+0400-045F; }
                /* latin */
                @font-face { font-family: 'Lora'; font-style: normal; font-weight: 400; src: url(https://fonts.gstatic.com/s/lora/latin.woff2) format('woff2'); unicode-range: U+0000-00FF; }
                CSS),
            'fonts.gstatic.com/*' => Http::response('wOF2latin'),
        ]);

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.fonts.google'), ['family' => 'Lora', 'weights' => [400]])
            ->assertOk()
            ->assertJsonFragment(['family' => 'Lora', 'source' => 'google']);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'cyr.woff2'));
        $this->assertCount(1, app(Fonts::class)->match('Lora'));
    }

    public function test_a_variable_google_font_keeps_every_weight(): void
    {
        $css = fn ($weight) => "/* latin */\n@font-face { font-family: 'Lora'; font-style: normal; font-weight: {$weight}; src: url(https://fonts.gstatic.com/s/lora/v.woff2) format('woff2'); unicode-range: U+0000-00FF; }";

        Http::fake([
            'fonts.googleapis.com/*' => Http::response($css(400)."\n".$css(700)),
            'fonts.gstatic.com/*' => Http::response('wOF2variable'),
        ]);

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.fonts.google'), ['family' => 'Lora', 'weights' => [400, 700]])->assertOk();

        $family = collect(app(Fonts::class)->families())->firstWhere('family', 'Lora');
        $this->assertSame([400, 500, 600, 700], $family['weights']);
        $this->assertSame([400, 700], app(Fonts::class)->match('Lora', 400)->first()['weight']);
    }

    public function test_a_failed_font_download_is_explained(): void
    {
        Http::fake([
            'fonts.googleapis.com/*' => Http::response("/* latin */\n@font-face { font-family: 'Lora'; font-style: normal; font-weight: 400; src: url(https://fonts.gstatic.com/s/lora/l.woff2) format('woff2'); }"),
            'fonts.gstatic.com/*' => fn () => throw new ConnectionException('timed out'),
        ]);

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.fonts.google'), ['family' => 'Lora'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Google Fonts couldn’t be reached. Try again in a moment.');
    }

    public function test_an_unknown_google_font_is_explained(): void
    {
        Http::fake(['fonts.googleapis.com/*' => Http::response('', 400)]);

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.fonts.google'), ['family' => 'Nope Sans'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Google Fonts doesn’t have Nope Sans in those weights.');
    }

    public function test_the_images_page_lists_entries_with_templates(): void
    {
        $this->template();
        $this->useTemplate('basic', ['collections' => ['articles']]);
        $this->entry();
        $this->entry('p', 'About', 'pages');

        $this->actingAs($this->cpUser())->get(cp_route('social-images.images.index'))
            ->assertOk()
            ->assertSee('Hello world')
            ->assertDontSee('About');
    }

    public function test_the_images_page_shows_an_out_of_date_image_with_the_one_still_in_use(): void
    {
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $entry = $this->entry();
        $old = app(Cards::class)->for($entry);
        $old->render();

        $this->template(layers: [['id' => 'title', 'type' => 'text', 'text' => 'New {title}']]);

        $this->actingAs($this->cpUser())->get(cp_route('social-images.images.index'))
            ->assertOk()
            ->assertSee(str_replace('/', '\\/', $old->path()), false);
    }

    public function test_regenerating_needs_edit_access_or_template_management(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->entry();

        $this->actingAs($this->cpUser(['view social images']))->postJson(cp_route('social-images.images.regenerate', 'one'))->assertForbidden();

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.images.regenerate', 'one'))
            ->assertOk()
            ->assertJsonPath('message', 'Image generated');
    }

    public function test_regenerating_reports_a_rate_limit(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->entry();
        $this->camera->failWith('Cloudflare screenshot generation failed: 429');

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.images.regenerate', 'one'))
            ->assertStatus(429)
            ->assertJsonPath('rate_limited', true);
    }

    public function test_the_status_endpoint_reports_the_image_once_it_exists(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->entry();
        $this->actingAs($this->cpUser());

        $this->getJson(cp_route('social-images.images.show', 'one'))->assertOk()->assertJsonPath('url', null);

        $this->app->terminate();

        $this->assertStringContainsString('/storage/social-images/one/og-', $this->getJson(cp_route('social-images.images.show', 'one'))->json('url'));
    }

    public function test_asking_for_a_missing_image_starts_making_it_and_offers_the_previous_one(): void
    {
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry();
        $this->actingAs($this->cpUser());

        $first = $this->getJson(cp_route('social-images.images.show', 'one'))->assertOk();
        $this->assertNull($first->json('url'));
        $this->assertNull($first->json('previous'));

        $this->app->terminate();
        $made = $this->getJson(cp_route('social-images.images.show', 'one'))->json('url');
        $this->assertStringContainsString('/og-', $made);

        // The template changes: the old image is offered while the new one is made.
        $this->template(layers: [['id' => 'title', 'type' => 'text', 'text' => 'New {title}']]);
        $stale = $this->getJson(cp_route('social-images.images.show', 'one'));
        $this->assertNull($stale->json('url'));
        $this->assertSame(strtok($made, '?'), strtok($stale->json('previous'), '?'));
        $this->assertFalse($stale->json('failed'));
        $this->assertSame('queued', $stale->json('state'));

        // While a render runs, the state says so, with how long so far.
        // (Each test request finishes the render the last one started, so
        // change the template again for one that hasn't been made.)
        $this->template(layers: [['id' => 'title', 'type' => 'text', 'text' => 'Newer {title}']]);
        $hash = app(Cards::class)->for(Entry::find('one'))->hash();
        Cache::put('social-images:rendering:one:og:'.$hash, time() - 2, 60);
        $running = $this->getJson(cp_route('social-images.images.show', 'one'));
        $this->assertSame('rendering', $running->json('state'));
        $this->assertGreaterThanOrEqual(2, $running->json('seconds'));
    }

    public function test_viewers_and_entries_with_their_own_image_start_no_render(): void
    {
        Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'social_image_override', 'field' => ['type' => 'assets', 'container' => 'assets', 'max_files' => 1]],
        ]])->save();
        $this->asset('mine.png');
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry('one');
        $this->entry('two', 'Own image', data: ['social_image_override' => 'mine.png']);
        config(['queue.default' => 'redis']);
        Queue::fake();

        // Someone who can only view: told it's waiting, but nothing starts.
        $this->actingAs($this->cpUser(['view social images', 'view articles entries']))
            ->getJson(cp_route('social-images.images.show', 'one'))
            ->assertOk()
            ->assertJsonPath('state', 'queued');

        // An entry with its own image: its generated one is never shown.
        $this->actingAs($this->cpUser())
            ->getJson(cp_route('social-images.images.show', 'two'))
            ->assertOk()
            ->assertJsonPath('override', fn ($url) => str_ends_with((string) $url, 'mine.png'));

        Queue::assertNotPushed(GenerateImage::class);

        // Someone who can edit it: the render starts.
        $this->actingAs($this->cpUser())->getJson(cp_route('social-images.images.show', 'one'))->assertOk();
        Queue::assertPushed(GenerateImage::class, 1);
    }

    public function test_the_settings_page_has_the_template_rules(): void
    {
        $this->template();

        $this->actingAs($this->cpUser(['configure addons']))->get(cp_route('addons.settings.edit', 'social-images-statamic'))
            ->assertOk()
            ->assertSee('Add rule')
            ->assertSee('SEO Pro');
    }
}
