<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\Library\Uploads;
use NineteenNinetyFour\SocialImages\Templates\InvalidTemplate;
use NineteenNinetyFour\SocialImages\Templates\JsonTemplate;
use NineteenNinetyFour\SocialImages\Templates\Porter;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use NineteenNinetyFour\SocialImages\Tests\FakeCamera;
use NineteenNinetyFour\SocialImages\Tests\TestCase;

class PorterTest extends TestCase
{
    protected function porter(): Porter
    {
        return app(Porter::class);
    }

    public function test_an_export_carries_its_uploads_and_site_fonts_but_not_bundled_ones(): void
    {
        $upload = app(Uploads::class)->put(FakeCamera::PNG, 'png');
        app(Fonts::class)->add('House Sans', 'wOF2fakefontdata', 'woff2', 700);

        $template = $this->template(layers: [
            ['id' => 'logo', 'type' => 'image', 'source' => ['upload:'.$upload]],
            ['id' => 'a', 'type' => 'text', 'text' => 'A', 'font' => ['family' => 'House Sans', 'weight' => 700]],
            ['id' => 'b', 'type' => 'text', 'text' => 'B', 'font' => ['family' => 'Inter']],
        ]);

        $export = $this->porter()->export($template);

        $this->assertSame('social-images/1', $export['schema']);
        $this->assertSame(['data:image/png;base64,'.base64_encode(FakeCamera::PNG)], array_values($export['uploads']));
        $this->assertSame(['House Sans'], array_column($export['fonts'], 'family'));
        $this->assertSame('data:font/woff2;base64,'.base64_encode('wOF2fakefontdata'), $export['fonts'][0]['data']);
    }

    public function test_a_site_asset_is_exported_as_an_upload(): void
    {
        $this->asset('logo.png', 40, 20);
        $template = $this->template(layers: [['id' => 'logo', 'type' => 'image', 'source' => ['asset:assets::logo.png']]]);

        $export = $this->porter()->export($template);
        $source = $export['layouts']['og']['layers'][0]['source'][0];

        $this->assertMatchesRegularExpression('/^upload:[a-f0-9]{40}\.png$/', $source);
        $this->assertArrayHasKey(substr($source, 7), $export['uploads']);
    }

    public function test_an_export_imports_into_a_clean_site(): void
    {
        $upload = app(Uploads::class)->put(FakeCamera::PNG, 'png');
        app(Fonts::class)->add('House Sans', 'wOF2fakefontdata', 'woff2', [400, 700]);

        $template = $this->template('brand', [
            ['id' => 'logo', 'type' => 'image', 'source' => ['upload:'.$upload]],
            ['id' => 'a', 'type' => 'text', 'text' => '{title}', 'font' => ['family' => 'House Sans', 'weight' => 700]],
        ]);
        $json = json_encode($this->porter()->export($template));

        File::deleteDirectory(self::libraryPath());
        app()->forgetInstance(Fonts::class);
        app(TemplateRepository::class)->refresh();

        $imported = $this->porter()->import($this->porter()->decode($json));

        $this->assertInstanceOf(JsonTemplate::class, $imported);
        $this->assertSame('brand', $imported->handle());
        $this->assertTrue(app(Uploads::class)->exists($upload));
        $this->assertTrue(app(Fonts::class)->has('House Sans'));
        $this->assertSame($template->toArray()['layouts'], $imported->toArray()['layouts']);
    }

    public function test_importing_twice_gives_a_new_handle(): void
    {
        $json = json_encode($this->porter()->export($this->template('brand')));

        $this->assertSame('brand_2', $this->porter()->import($this->porter()->decode($json))->handle());
    }

    public function test_a_disguised_or_unknown_upload_is_refused(): void
    {
        $this->expectException(InvalidTemplate::class);

        $this->porter()->import([
            'schema' => 'social-images/1',
            'layouts' => ['og' => []],
            'uploads' => ['x.png' => 'data:image/png;base64,'.base64_encode('<svg onload=alert(1)>')],
        ]);
    }

    public function test_html_masquerading_as_an_image_type_is_refused(): void
    {
        $this->expectException(InvalidTemplate::class);

        $this->porter()->import([
            'schema' => 'social-images/1',
            'layouts' => ['og' => []],
            'uploads' => ['x.html' => 'data:text/html;base64,'.base64_encode('<script>alert(1)</script>')],
        ]);
    }

    public function test_bad_json_is_explained(): void
    {
        try {
            $this->porter()->decode('{"nope":');
            $this->fail();
        } catch (InvalidTemplate $exception) {
            $this->assertStringContainsString('isn’t valid JSON', $exception->getMessage());
        }

        $this->expectException(InvalidTemplate::class);
        $this->porter()->decode('{"name":"something else"}');
    }

    public function test_urls_must_be_https_and_public(): void
    {
        Http::fake();

        foreach (['http://1.1.1.1/t.json', 'https://127.0.0.1/t.json', 'https://10.0.0.5/t.json', 'https://169.254.169.254/latest', 'https://[::1]/t.json', 'file:///etc/passwd', 'https://user:pass@1.1.1.1/x',
            'https://100.100.100.200/latest', 'https://198.18.0.1/x', 'https://[64:ff9b::a9fe:a9fe]/x', 'https://[2002:a9fe:a9fe::]/x', 'https://1.1.1.1:8443/t.json'] as $url) {
            try {
                $this->porter()->fetch($url);
                $this->fail("{$url} was fetched");
            } catch (InvalidTemplate) {
                $this->addToAssertionCount(1);
            }
        }

        Http::assertNothingSent();
    }

    public function test_a_public_url_is_fetched_without_following_redirects(): void
    {
        Http::fake(['1.1.1.1/*' => Http::response(json_encode(['schema' => 'social-images/1', 'title' => 'Remote', 'layouts' => ['og' => []]]))]);

        $data = $this->porter()->fetch('https://1.1.1.1/template.json');

        $this->assertSame('Remote', $data['title']);
        Http::assertSent(fn ($request) => $request->url() === 'https://1.1.1.1/template.json');
    }

    public function test_a_github_file_page_is_fetched_raw(): void
    {
        $method = new \ReflectionMethod(Porter::class, 'rawGitHubUrl');

        $this->assertSame(
            'https://raw.githubusercontent.com/1994limited/social-images-statamic/main/gallery/editorial.json',
            $method->invoke($this->porter(), 'https://github.com/1994limited/social-images-statamic/blob/main/gallery/editorial.json'),
        );
    }
}
