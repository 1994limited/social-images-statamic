<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;

class TagsTest extends TestCase
{
    protected function render(string $template, array $context = []): string
    {
        return trim((string) Antlers::parse($template, $context, true));
    }

    protected function generated(string $title = 'Hello world'): Entry
    {
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $entry = $this->entry(title: $title);
        Storage::disk('public')->put(app(Cards::class)->for($entry)->path(), 'png');

        return $entry;
    }

    public function test_the_url_of_the_current_pages_image(): void
    {
        $entry = $this->generated();

        $this->assertStringEndsWith(app(Cards::class)->for($entry)->path(), $this->render('{{ social_image }}', ['id' => 'one']));
        $this->assertSame($this->render('{{ social_image }}', ['id' => 'one']), $this->render('{{ social_image:url entry="one" }}'));
    }

    public function test_the_pair_has_the_details(): void
    {
        $this->generated();

        $this->assertSame('1200x630 generated', $this->render('{{ social_image entry="one" }}{{ width }}x{{ height }} {{ source }}{{ /social_image }}'));
    }

    public function test_meta_tags_are_escaped(): void
    {
        $this->generated('Fish "&" chips');

        $meta = $this->render('{{ social_image:meta }}', ['id' => 'one']);

        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $meta);
        $this->assertStringContainsString('<meta property="og:image:alt" content="Fish &quot;&amp;&quot; chips">', $meta);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $meta);
    }

    public function test_the_tag_works_beside_the_social_image_fields(): void
    {
        Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['import' => 'social-images::social_image'],
        ]])->save();
        $this->asset();

        $entry = $this->generated();
        $context = $entry->toAugmentedArray();

        $this->assertArrayHasKey('social_image_override', $context);
        $this->assertStringEndsWith(app(Cards::class)->for($entry)->path(), $this->render('{{ social_image }}', $context));
        $this->assertStringContainsString('@font-face{font-family:"Inter"', $this->render('{{ social_image:font family="Inter" }}', $context));
    }

    public function test_nothing_without_an_image(): void
    {
        $this->assertSame('', $this->render('{{ social_image:meta }}'));
        $this->assertSame('', $this->render('{{ social_image }}'));
    }
}
