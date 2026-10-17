<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_an_entry_gets_an_image_from_its_template(): void
    {
        $this->template();
        $this->useTemplate('basic');

        $entry = $this->entry();
        $this->app->terminate();

        $card = app(Cards::class)->for($entry);

        $this->assertNotNull($card);
        $this->assertTrue($card->exists());
        $this->assertStringContainsString('Hello world', $this->pageData($this->camera->captures[0]['html']));
    }
}
