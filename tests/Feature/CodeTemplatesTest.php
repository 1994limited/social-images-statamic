<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Templates\InvalidTemplate;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use NineteenNinetyFour\SocialImages\Templates\ViewTemplate;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Facades\Entry;

class CodeTemplatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['social-images.views' => [
            'article' => ['title' => 'Article', 'view' => 'social/card', 'layouts' => ['og' => [1200, 630], 'square' => ['width' => 1080, 'height' => 1080]]],
            'plain' => ['view' => 'social/blade'],
            'broken' => ['title' => 'No view'],
        ]]);

        app(TemplateRepository::class)->refresh();
    }

    public function test_views_in_the_config_are_templates(): void
    {
        $templates = app(TemplateRepository::class)->all();

        $this->assertInstanceOf(ViewTemplate::class, $templates['article']);
        $this->assertSame('Plain', $templates['plain']->title());
        $this->assertArrayNotHasKey('broken', $templates->all());
        $this->assertSame(['og' => ['width' => 1200, 'height' => 630], 'square' => ['width' => 1080, 'height' => 1080]], $templates['article']->layouts());
    }

    public function test_an_antlers_view_gets_the_entry_fonts_and_the_renderer(): void
    {
        $html = app(TemplateRepository::class)->find('article')->html($this->entry(), 'square');

        $this->assertStringContainsString('<h1 data-fit="80" data-fit-min="32">Hello world</h1>', $html);
        $this->assertStringContainsString('Articles · square', $html);
        $this->assertStringContainsString('width: 1080px', $html);
        $this->assertStringContainsString('@font-face{font-family:"Inter";font-style:normal;font-weight:100 900;font-display:block;src:url(data:font/woff2;base64,', $html);
        $this->assertStringContainsString('SocialImages.ready();</script></body>', $html);
    }

    public function test_a_blade_view_works_too(): void
    {
        $html = app(TemplateRepository::class)->find('plain')->html($this->entry(), 'og');

        $this->assertStringContainsString('<h1>Hello world</h1><p>1200</p>', $html);
    }

    public function test_any_change_to_the_entry_changes_a_code_templates_fingerprint(): void
    {
        $this->useTemplate('article');
        $card = app(Cards::class)->for($this->entry());
        $hash = $card->hash();

        Entry::find('one')->set('anything', 'x')->save();

        $this->assertNotSame($hash, app(Cards::class)->for(Entry::find('one'))->hash());
    }

    public function test_a_designer_template_cannot_take_a_code_templates_handle(): void
    {
        $this->expectException(InvalidTemplate::class);

        app(TemplateRepository::class)->save('article', ['schema' => 'social-images/1', 'layouts' => ['og' => []]]);
    }
}
