<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\Tests\TestCase;

class CommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        config(['social-images.rate_limit_wait' => 0]);
    }

    public function test_it_makes_missing_images_and_skips_current_ones(): void
    {
        $this->entry('a');
        $this->entry('b', 'Second');

        $this->artisan('social-images:generate')->assertSuccessful();
        $this->assertCount(2, $this->camera->captures);

        $this->artisan('social-images:generate')->expectsOutputToContain('up to date')->assertSuccessful();
        $this->assertCount(2, $this->camera->captures);

        $this->artisan('social-images:generate', ['--force' => true, '--entry' => ['a']])->assertSuccessful();
        $this->assertCount(3, $this->camera->captures);
    }

    public function test_it_filters_by_collection(): void
    {
        $this->entry('a');
        $this->entry('p', collection: 'pages');

        $this->artisan('social-images:generate', ['--collection' => ['pages']])->assertSuccessful();

        $this->assertCount(1, $this->camera->captures);
    }

    public function test_a_rate_limited_render_is_tried_again(): void
    {
        $this->entry('a');
        $this->camera->failWith('Cloudflare screenshot generation failed: {"errors":[{"code":429,"message":"Rate limit exceeded"}]}', 2);

        $this->artisan('social-images:generate')->expectsOutputToContain('Rate limited')->assertSuccessful();

        $this->assertCount(1, $this->camera->captures);
    }

    public function test_it_stops_when_the_limit_keeps_refusing(): void
    {
        $this->entry('a');
        $this->entry('b', 'Second');
        $this->camera->failWith('429 Too Many Requests', 10);

        $this->artisan('social-images:generate')->expectsOutputToContain('Run this again later')->assertFailed();

        $this->assertCount(0, $this->camera->captures);
    }

    public function test_other_failures_are_reported_and_the_run_goes_on(): void
    {
        $this->entry('a');
        $this->entry('b', 'Second');
        $this->camera->failWith('Chrome not found');

        $this->artisan('social-images:generate')->expectsOutputToContain('Chrome not found')->assertFailed();

        $this->assertCount(1, $this->camera->captures);
    }

    public function test_it_can_queue_instead(): void
    {
        $this->entry('a');
        Queue::fake();

        $this->artisan('social-images:generate', ['--queue' => true])->assertSuccessful();

        Queue::assertPushed(GenerateImage::class, fn ($job) => $job->entryId === 'a');
        $this->assertCount(0, $this->camera->captures);
    }
}
