<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use NineteenNinetyFour\SocialImages\Library\Uploads;
use NineteenNinetyFour\SocialImages\Rendering\Page;
use NineteenNinetyFour\SocialImages\Tests\FakeCamera;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Facades\Blueprint;

class RenderingTest extends TestCase
{
    protected function payload(string $html): array
    {
        preg_match('/var p=(\{.*?\});SocialImages\.render/s', $html, $matches);

        return json_decode($matches[1], true);
    }

    public function test_the_page_inlines_the_renderer_and_the_resolved_layout(): void
    {
        $template = $this->template();
        $html = $template->html($this->entry(), 'og');

        $this->assertStringStartsWith('<!doctype html>', $html);
        $this->assertStringContainsString('window.SocialImages=', $html);
        $this->assertStringNotContainsString('export function', $html);
        $this->assertStringContainsString('data-social-ready', $html);

        $payload = $this->payload($html);
        $this->assertSame(1200, $payload['layout']['width']);
        $this->assertSame(['text' => 'Hello world'], $payload['data']['layers']['title']);
    }

    public function test_nothing_is_loaded_over_the_network(): void
    {
        $html = $this->template()->html($this->entry(), 'og');

        $this->assertDoesNotMatchRegularExpression('#(src|href)=["\']?https?://#i', $html);
        $this->assertStringNotContainsString('url(http', $html);
    }

    public function test_entry_text_cannot_break_out_of_the_script(): void
    {
        $html = $this->template()->html($this->entry(title: '&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt;'), 'og');

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertSame('</script><script>alert(1)</script>', $this->payload($html)['data']['layers']['title']['text']);
    }

    public function test_only_the_fonts_used_are_embedded_as_data_uris(): void
    {
        $template = $this->template(layers: [
            ['id' => 't', 'type' => 'text', 'text' => '{title}', 'font' => ['family' => 'Fraunces', 'weight' => 600]],
        ]);

        $fonts = $this->payload($template->html($this->entry(), 'og'))['data']['fonts'];

        $this->assertCount(2, $fonts); // Latin and Latin Extended
        $this->assertSame(['Fraunces'], array_values(array_unique(array_column($fonts, 'family'))));
        $this->assertStringStartsWith('data:font/woff2;base64,', $fonts[0]['src']);
        $this->assertSame('100 900', $fonts[0]['weight']);
    }

    public function test_empty_text_and_failed_conditions_hide_layers(): void
    {
        $template = $this->template(layers: [
            ['id' => 'sub', 'type' => 'text', 'text' => '{subtitle}'],
            ['id' => 'badge', 'type' => 'shape', 'when' => 'featured'],
            ['id' => 'shown', 'type' => 'shape', 'when' => 'title'],
            ['id' => 'pic', 'type' => 'image', 'source' => ['field:featured_image']],
        ]);

        $layers = $this->payload($template->html($this->entry(), 'og'))['data']['layers'];

        $this->assertSame(['hidden' => true], $layers['sub']);
        $this->assertSame(['hidden' => true], $layers['badge']);
        $this->assertArrayNotHasKey('shown', $layers);
        $this->assertSame(['hidden' => true], $layers['pic']);
    }

    public function test_an_asset_field_is_cropped_by_glide_and_inlined(): void
    {
        Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'featured_image', 'field' => ['type' => 'assets', 'container' => 'assets', 'max_files' => 1]],
        ]])->save();

        $this->asset('photo.png', 1600, 1200);

        $template = $this->template(layers: [
            ['id' => 'pic', 'type' => 'image', 'source' => ['field:featured_image'], 'width' => 400, 'height' => 300],
        ]);

        $src = $this->payload($template->html($this->entry(data: ['featured_image' => 'photo.png']), 'og'))['data']['layers']['pic']['src'];

        $this->assertStringStartsWith('data:image/webp;base64,', $src);
        [$width, $height] = getimagesizefromstring(base64_decode(substr($src, strlen('data:image/webp;base64,'))));
        $this->assertSame([400, 300], [$width, $height]);
    }

    public function test_the_first_source_with_an_image_is_used(): void
    {
        $upload = app(Uploads::class)->put(FakeCamera::PNG, 'png');

        $template = $this->template(layers: [
            ['id' => 'pic', 'type' => 'image', 'source' => ['field:featured_image', 'upload:'.$upload]],
        ]);

        $src = $this->payload($template->html($this->entry(), 'og'))['data']['layers']['pic']['src'];

        $this->assertSame('data:image/png;base64,'.base64_encode(FakeCamera::PNG), $src);
    }

    public function test_code_templates_get_the_renderer_added(): void
    {
        $page = app(Page::class)->view('<html><body><h1 data-fit="80">Hi</h1></body></html>');

        $this->assertStringContainsString('SocialImages.ready();</script></body>', $page);
    }

    public function test_an_entrys_language_is_the_pages(): void
    {
        $this->assertStringContainsString('<html lang="en">', $this->template()->html($this->entry(), 'og'));
    }
}
