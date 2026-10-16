<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use NineteenNinetyFour\SocialImages\Rendering\Tokens;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;

class TokensTest extends TestCase
{
    protected function tokens(): Tokens
    {
        return app(Tokens::class);
    }

    public function test_title_and_fields_fill_in(): void
    {
        $entry = $this->entry(data: ['subtitle' => 'A <em>small</em> &amp; tidy   tale']);

        $this->assertSame('Hello world — A small & tidy tale', $this->tokens()->text('{title} — {subtitle}', $entry));
        $this->assertSame('A small & tidy tale', $this->tokens()->text('{field:subtitle}', $entry));
    }

    public function test_the_first_token_with_a_value_wins(): void
    {
        $entry = $this->entry(data: ['excerpt' => 'Short']);

        $this->assertSame('Short', $this->tokens()->text('{subtitle ?? excerpt ?? "Read more"}', $entry));
        $this->assertSame('Read more', $this->tokens()->text('{subtitle ?? "Read more"}', $entry));
        $this->assertSame('', $this->tokens()->text('{subtitle}', $entry));
    }

    public function test_site_collection_date_and_url_tokens(): void
    {
        $entry = $this->entry();

        $this->assertSame('Articles', $this->tokens()->text('{collection:title}', $entry));
        $this->assertSame('articles', $this->tokens()->text('{collection:handle}', $entry));
        $this->assertSame('16 October 2026', $this->tokens()->text('{date}', $entry));
        $this->assertSame('16 Oct 2026', $this->tokens()->text('{date:j M Y}', $entry));
        $this->assertSame('/articles/hello-world', $this->tokens()->text('{path}', $entry));
        $this->assertSame(Site::default()->name(), $this->tokens()->text('{site:name}', $entry));
    }

    public function test_a_related_users_name_through_the_author_field(): void
    {
        Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'author', 'field' => ['type' => 'users', 'max_items' => 1]],
        ]])->save();

        User::make()->id('ada')->email('ada@example.com')->set('name', 'Ada Lovelace')->save();

        $entry = $this->entry(data: ['author' => 'ada']);

        $this->assertSame('By Ada Lovelace', $this->tokens()->text('By {author}', $entry));
        $this->assertSame('By Ada Lovelace', $this->tokens()->text('By {author:name}', $entry));
        $this->assertSame('ada@example.com', $this->tokens()->text('{author.email}', $entry));
    }

    public function test_escaped_braces_stay(): void
    {
        $this->assertSame('{title} is Hello world', $this->tokens()->text('\{title\} is {title}', $this->entry()));
    }

    public function test_without_an_entry_tokens_show_samples(): void
    {
        $this->assertSame(Tokens::SAMPLES['title'], $this->tokens()->text('{title}', null));
        $this->assertSame('Subtitle', $this->tokens()->text('{subtitle}', null));
        $this->assertSame('Read more', $this->tokens()->text('{"Read more"}', null));
    }

    public function test_filled_decides_a_layers_show_only_when(): void
    {
        $entry = $this->entry(data: ['subtitle' => 'Yes']);

        $this->assertTrue($this->tokens()->filled(null, $entry));
        $this->assertTrue($this->tokens()->filled('subtitle', $entry));
        $this->assertTrue($this->tokens()->filled('{subtitle}', $entry));
        $this->assertFalse($this->tokens()->filled('excerpt', $entry));
    }

    public function test_an_entries_field_lists_titles(): void
    {
        Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'related', 'field' => ['type' => 'entries', 'collections' => ['pages']]],
        ]])->save();

        Entry::make()->collection('pages')->id('p1')->slug('a')->data(['title' => 'About'])->save();
        Entry::make()->collection('pages')->id('p2')->slug('b')->data(['title' => 'Contact'])->save();

        $this->assertSame('About, Contact', $this->tokens()->text('{related}', $this->entry(data: ['related' => ['p1', 'p2']])));
    }
}
