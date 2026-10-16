<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\Templates\Porter;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use NineteenNinetyFour\SocialImages\Tests\FakeCamera;
use NineteenNinetyFour\SocialImages\Tests\TestCase;

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

    public function test_the_settings_page_has_the_template_rules(): void
    {
        $this->template();

        $this->actingAs($this->cpUser(['configure addons']))->get(cp_route('addons.settings.edit', 'social-images-statamic'))
            ->assertOk()
            ->assertSee('Add rule')
            ->assertSee('SEO Pro');
    }
}
