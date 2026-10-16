<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\Templates\Porter;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use NineteenNinetyFour\SocialImages\Tests\TestCase;

/**
 * The examples in docs/php-api.md, as written, and the gallery's files.
 */
class DocsTest extends TestCase
{
    public function test_the_php_api_examples(): void
    {
        $this->template('editorial');
        $this->settings(['templates' => [['template' => 'editorial']], 'generate_on_save' => false]);
        $entry = $this->entry();

        $cards = app(Cards::class);

        $card = $cards->for($entry, 'og');
        $this->assertFalse($card->exists());
        $this->assertSame($card->path(), $card->render());
        $this->assertTrue($card->exists());

        $image = $cards->image($entry);
        $this->assertSame(['url', 'width', 'height', 'alt', 'source'], array_keys($image));
        $this->assertSame('generated', $image['source']);

        $this->assertSame('editorial', $cards->templateFor($entry)->handle());
        $this->assertSame(['og'], $cards->layoutsFor($entry));

        Queue::fake();
        GenerateImage::dispatch($entry->id(), 'og', force: true);
        Queue::assertPushed(GenerateImage::class);

        $templates = app(TemplateRepository::class);
        $porter = app(Porter::class);
        $json = json_encode($porter->export($templates->find('editorial')));
        $template = $porter->import($porter->decode($json));
        $this->assertSame('editorial_2', $template->handle());
    }

    public function test_every_gallery_template_imports_and_matches_its_starter(): void
    {
        $porter = app(Porter::class);
        $starters = app(TemplateRepository::class)->starters();

        foreach (glob(__DIR__.'/../../gallery/*.json') as $file) {
            $key = basename($file, '.json');
            $template = $porter->import($porter->decode(file_get_contents($file)));

            $this->assertArrayHasKey($key, $starters, "{$key} has no starter");
            $this->assertSame(Schema::normalize($starters[$key])['layouts'], $template->toArray()['layouts'], $key);
            $this->assertFileExists(__DIR__."/../../gallery/previews/{$key}.png");
        }

        $this->assertCount(count($starters), glob(__DIR__.'/../../gallery/*.json'));
    }

    public function test_links_between_the_docs_point_at_files_that_exist(): void
    {
        $root = realpath(__DIR__.'/../..');
        $files = [...glob($root.'/docs/*.md'), $root.'/README.md', $root.'/gallery/README.md'];

        foreach ($files as $file) {
            preg_match_all('/\]\((?!https?:|mailto:|#)([^)#\s]+)(#[^)]*)?\)/', file_get_contents($file), $links, PREG_SET_ORDER);

            foreach ($links as [, $target]) {
                $this->assertFileExists(dirname($file).'/'.$target, basename($file)." links to {$target}");
            }
        }
    }
}
