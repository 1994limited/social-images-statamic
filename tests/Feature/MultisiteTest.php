<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

class MultisiteTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.system.multisite', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Site::setSites([
            'en' => ['name' => 'English', 'url' => '/', 'locale' => 'en_GB'],
            'fr' => ['name' => 'Français', 'url' => '/fr/', 'locale' => 'fr_FR'],
        ]);

        Collection::make('articles')->sites(['en', 'fr'])->routes('/articles/{slug}')->dated(true)->save();
    }

    protected function french(): \Statamic\Contracts\Entries\Entry
    {
        $entry = Entry::make()->collection('articles')->locale('fr')->id('fr1')->slug('bonjour')->date('2026-10-16')->data(['title' => 'Bonjour']);
        $entry->save();

        return $entry;
    }

    public function test_dates_are_in_the_entrys_language_wherever_the_image_is_made(): void
    {
        $this->template(layers: [['id' => 'd', 'type' => 'text', 'text' => '{date} · {site:name}']]);
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);

        $entry = $this->french();
        $card = app(Cards::class)->for($entry);

        Site::setCurrent('en');
        app()->setLocale('en');

        $this->assertStringContainsString('16 octobre 2026 · Français', $this->pageData($card->template->html($entry, 'og')));
        $this->assertSame('en', app()->getLocale(), 'The locale is put back.');
        $this->assertSame('en', Site::current()->handle());
        $this->assertStringContainsString('<html lang="fr">', $card->template->html($entry, 'og'));

        // The same fingerprint whatever site and language the request is in.
        $hash = $card->hash();
        Site::setCurrent('fr');
        app()->setLocale('fr');
        $this->assertSame($hash, app(Cards::class)->for(Entry::find('fr1'))->hash());
    }

    public function test_rules_can_be_for_one_site(): void
    {
        $this->template('french');
        $this->settings(['templates' => [['sites' => ['fr'], 'template' => 'french']]]);

        $this->assertSame('french', app(Cards::class)->templateFor($this->french())->handle());
        $this->assertNull(app(Cards::class)->templateFor($this->entry('en1')));
    }
}
