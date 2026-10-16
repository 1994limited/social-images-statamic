<?php

namespace NineteenNinetyFour\SocialImages\Tests\Unit;

use NineteenNinetyFour\SocialImages\Templates\InvalidTemplate;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use NineteenNinetyFour\SocialImages\Tests\TestCase;

class SchemaTest extends TestCase
{
    protected function minimal(array $layers = [], array $layout = []): array
    {
        return ['schema' => Schema::VERSION, 'title' => 'T', 'layouts' => ['og' => [...$layout, 'layers' => $layers]]];
    }

    public function test_a_template_needs_the_og_layout_and_this_schema_version(): void
    {
        $this->expectException(InvalidTemplate::class);
        Schema::normalize(['schema' => Schema::VERSION, 'layouts' => ['square' => []]]);
    }

    public function test_another_schema_version_is_refused(): void
    {
        $this->expectException(InvalidTemplate::class);
        Schema::normalize(['schema' => 'social-images/9', 'layouts' => ['og' => []]]);
    }

    public function test_unknown_keys_are_dropped_everywhere(): void
    {
        $data = Schema::normalize([
            ...$this->minimal([[
                'type' => 'text', 'id' => 't', 'text' => 'Hi', 'html' => '<script>alert(1)</script>', 'style' => 'color:red',
                'background' => ['type' => 'solid', 'color' => '#fff', 'css' => 'url(https://evil.test)'],
            ]]),
            'css' => 'body{}',
            'script' => 'alert(1)',
        ]);

        $this->assertArrayNotHasKey('css', $data);
        $this->assertArrayNotHasKey('script', $data);

        $layer = $data['layouts']['og']['layers'][0];
        $this->assertArrayNotHasKey('html', $layer);
        $this->assertArrayNotHasKey('style', $layer);
        $this->assertSame(['type' => 'solid', 'color' => '#fff'], $layer['background']);
    }

    public function test_layers_of_unknown_types_are_dropped(): void
    {
        $data = Schema::normalize($this->minimal([['type' => 'html', 'html' => '<b>x</b>'], ['type' => 'shape']]));

        $this->assertCount(1, $data['layouts']['og']['layers']);
        $this->assertSame('shape', $data['layouts']['og']['layers'][0]['type']);
    }

    public function test_colours_must_be_hex(): void
    {
        $this->assertSame('#ff0000', Schema::color('#FF0000'));
        $this->assertSame('#00000000', Schema::color('transparent'));
        $this->assertNull(Schema::color('red'));
        $this->assertNull(Schema::color('#fff;background:url(x)'));
        $this->assertNull(Schema::color('rgb(0,0,0)'));
    }

    public function test_gradients_keep_valid_stops_in_order(): void
    {
        $fill = Schema::fill(['type' => 'linear', 'angle' => 45, 'stops' => [
            ['color' => '#000', 'position' => 100],
            ['color' => 'url(x)', 'position' => 50],
            ['color' => '#fff', 'position' => 0],
        ]]);

        $this->assertSame(['type' => 'linear', 'angle' => 45, 'stops' => [
            ['color' => '#fff', 'position' => 0],
            ['color' => '#000', 'position' => 100],
        ]], $fill);

        $this->assertSame(['type' => 'solid', 'color' => '#000'], Schema::fill(['type' => 'radial', 'stops' => [['color' => '#000']]]));
    }

    public function test_numbers_are_clamped(): void
    {
        $layer = Schema::layer(['type' => 'text', 'x' => 99999, 'opacity' => 4, 'size' => 'big', 'min_size' => 900, 'max_lines' => -3]);

        $this->assertSame(4800, $layer['x']);
        $this->assertSame(1, $layer['opacity']);
        $this->assertSame(64, $layer['size']);
        $this->assertSame(64, $layer['min_size']);
        $this->assertSame(0, $layer['max_lines']);
    }

    public function test_layout_sizes_are_kept_between_200_and_2400(): void
    {
        $data = Schema::normalize($this->minimal([], ['width' => 50, 'height' => 99999]));

        $this->assertSame(200, $data['layouts']['og']['width']);
        $this->assertSame(2400, $data['layouts']['og']['height']);
    }

    public function test_image_sources_are_fields_assets_or_uploads(): void
    {
        $sources = Schema::sources([
            'field:featured_image',
            'field:author.avatar',
            'asset:assets::team/logo.png',
            'upload:'.str_repeat('a', 40).'.png',
            'https://evil.test/x.png',
            'asset:assets::../../.env',
            'upload:../x.png',
            'field:bad handle',
            'javascript:alert(1)',
        ]);

        $this->assertSame([
            'field:featured_image',
            'field:author.avatar',
            'asset:assets::team/logo.png',
            'upload:'.str_repeat('a', 40).'.png',
        ], $sources);
    }

    public function test_font_families_are_plain_names(): void
    {
        $this->assertSame('Playfair Display', Schema::family('  Playfair   Display '));
        $this->assertNull(Schema::family('Evil"; } body { color: red'));
        $this->assertNull(Schema::family('url(x)'));

        $layer = Schema::layer(['type' => 'text', 'font' => ['family' => 'x"y', 'weight' => 650, 'style' => 'oblique']]);
        $this->assertSame(['family' => 'Inter', 'weight' => 700, 'style' => 'normal'], $layer['font']);
    }

    public function test_layer_ids_are_unique_within_a_layout(): void
    {
        $data = Schema::normalize($this->minimal([
            ['type' => 'shape', 'id' => 'a'],
            ['type' => 'shape', 'id' => 'a'],
            ['type' => 'shape', 'id' => '<bad>'],
        ]));

        $ids = collect($data['layouts']['og']['layers'])->pluck('id');

        $this->assertSame('a', $ids[0]);
        $this->assertCount(3, $ids->unique());
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $ids[2]);
    }

    public function test_text_keeps_line_breaks_but_not_control_characters(): void
    {
        $layer = Schema::layer(['type' => 'text', 'text' => "One\r\nTwo\x07"]);

        $this->assertSame("One\nTwo", $layer['text']);
    }

    public function test_layer_and_layout_counts_are_capped(): void
    {
        $layers = array_fill(0, 100, ['type' => 'shape']);
        $layouts = ['og' => ['layers' => $layers]];

        foreach (range(1, 20) as $n) {
            $layouts['extra_'.$n] = [];
        }

        $data = Schema::normalize(['schema' => Schema::VERSION, 'layouts' => $layouts]);

        $this->assertCount(Schema::MAX_LAYERS, $data['layouts']['og']['layers']);
        $this->assertCount(Schema::MAX_LAYOUTS, $data['layouts']);
        $this->assertSame('og', array_key_first($data['layouts']));
    }

    public function test_presets_give_extra_layouts_their_size(): void
    {
        $data = Schema::normalize(['schema' => Schema::VERSION, 'layouts' => ['square' => [], 'og' => []]]);

        $this->assertSame(['og', 'square'], array_keys($data['layouts']));
        $this->assertSame(1080, $data['layouts']['square']['width']);
        $this->assertSame(1080, $data['layouts']['square']['height']);
    }
}
