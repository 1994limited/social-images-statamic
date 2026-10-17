<?php

namespace NineteenNinetyFour\SocialImages\Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use NineteenNinetyFour\SocialImages\Core\Rendering\Drivers\FakeRenderer;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\NotConfigured;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\QuotaExhausted;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RateLimited;
use NineteenNinetyFour\SocialImages\Core\Rendering\Exceptions\RenderFailed;
use NineteenNinetyFour\SocialImages\Core\Rendering\RendererManager;
use NineteenNinetyFour\SocialImages\Core\Support\Lang;
use NineteenNinetyFour\SocialImages\Images\Camera;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Jobs\GenerateImage;
use NineteenNinetyFour\SocialImages\Rendering\LaravelScreenshotRenderer;
use NineteenNinetyFour\SocialImages\SocialImages;
use NineteenNinetyFour\SocialImages\Tests\TestCase;
use RuntimeException;

class RenderersTest extends TestCase
{
    protected FakeRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        // The real Camera, rendering through the manager with the fake driver.
        config(['social-images.renderer.driver' => 'fake']);
        $this->app->forgetInstance(RendererManager::class);
        $this->app->instance(Camera::class, new Camera);
        $this->renderer = new FakeRenderer;
        app(RendererManager::class)->swap('fake', $this->renderer);
    }

    public function test_laravel_screenshot_stays_the_default(): void
    {
        $this->assertSame('laravel-screenshot', (require __DIR__.'/../../config/social-images.php')['renderer']['driver']);

        config(['social-images.renderer.driver' => null]);
        $this->app->forgetInstance(RendererManager::class);

        $this->assertSame('laravel-screenshot', app(RendererManager::class)->defaultDriver());
        $this->assertInstanceOf(LaravelScreenshotRenderer::class, app(RendererManager::class)->driver());
        $this->assertEqualsCanonicalizing(['browsershot', 'cloudflare', 'fake', 'laravel-screenshot'], app(RendererManager::class)->names());
    }

    public function test_the_camera_renders_through_the_configured_driver(): void
    {
        $this->assertSame(FakeRenderer::PNG, app(Camera::class)->capture('<p>Hi</p>', 1200, 630));

        $this->assertCount(1, $this->renderer->requests);
        $this->assertSame([1200, 630, '<p>Hi</p>'], [$this->renderer->requests[0]->width, $this->renderer->requests[0]->height, $this->renderer->requests[0]->html]);
    }

    public function test_images_render_through_it(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry();

        $this->artisan('social-images:generate')->assertSuccessful();

        $this->assertCount(1, $this->renderer->requests);
        $this->assertStringContainsString('Hello world', $this->pageData($this->renderer->requests[0]->html));
    }

    public function test_a_driver_can_be_added(): void
    {
        $mine = new FakeRenderer;
        SocialImages::extend('mine', fn (array $options) => $mine);
        config(['social-images.renderer.driver' => 'mine']);

        $this->assertSame($mine, SocialImages::renderer('mine'));

        $this->app->forgetInstance(RendererManager::class);
        SocialImages::extend('mine', fn (array $options) => $mine);
        app(Camera::class)->capture('<p>Mine</p>', 100, 100);

        $this->assertCount(1, $mine->requests);
    }

    public function test_the_core_package_speaks_the_sites_language(): void
    {
        // addLines() can't take a key with a full stop in it; a file can.
        $dir = self::libraryPath().'/lang';
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/fr.json', json_encode(['Rendered a test image in :ms ms.' => 'Test réussi en :ms ms.']));
        app('translator')->addJsonPath($dir);
        app()->setLocale('fr');

        $this->assertSame('Test réussi en 5 ms.', Lang::get('Rendered a test image in :ms ms.', ['ms' => 5]));
    }

    public function test_a_queued_render_waits_as_long_as_the_service_asks(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry();
        $this->renderer->failWith(new RateLimited('Slow down', retryAfter: 42));

        $job = (new GenerateImage('one'))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(42);
    }

    public function test_a_queued_render_waits_for_a_used_up_allowance_to_reset(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $entry = $this->entry();
        $this->renderer->failWith(new QuotaExhausted('No more today', new DateTimeImmutable('+2 hours')));

        $job = (new GenerateImage('one'))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased();
        $this->assertGreaterThan(7000, $job->job->releaseDelay);

        // Page views don't try again in the meantime.
        $hash = app(Cards::class)->for($entry)->hash();
        $this->assertTrue(GenerateImage::waiting('one', 'og', $hash));
        $this->assertFalse(GenerateImage::recentlyFailed('one', 'og', $hash));
    }

    public function test_a_waiting_render_shows_as_queued_and_isnt_queued_again(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $entry = $this->entry();
        $this->renderer->failWith(new RateLimited('Slow down'));

        $job = (new GenerateImage('one'))->withFakeQueueInteractions();
        $job->handle();

        // No wait given: the job's own backoff.
        $job->assertReleased(15);

        $this->actingAs($this->cpUser())->getJson(cp_route('social-images.images.show', 'one'))
            ->assertJsonPath('state', 'queued')
            ->assertJsonPath('failed', false);
        $this->app->terminate();

        // Asking about it didn't start another render.
        $this->assertCount(0, $this->renderer->requests);
    }

    public function test_a_render_is_marked_failed_once_its_tries_run_out(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $entry = $this->entry();
        $this->renderer->failWith(new RateLimited('Slow down', retryAfter: 10));

        $job = (new GenerateImage('one'))->withFakeQueueInteractions();
        $job->job->attempts = 4;

        try {
            $job->handle();
            $this->fail('The last try didn\'t throw.');
        } catch (RateLimited) {
            $hash = app(Cards::class)->for($entry)->hash();
            $this->assertTrue(GenerateImage::recentlyFailed('one', 'og', $hash));
        }
    }

    public function test_a_render_outside_a_queue_still_throws(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry();
        $this->renderer->failWith(new RateLimited('Slow down', retryAfter: 42));

        $this->expectException(RateLimited::class);

        (new GenerateImage('one'))->handle();
    }

    public function test_the_command_stops_at_once_when_the_allowance_is_gone(): void
    {
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry('a');
        $this->entry('b', 'Second');
        $this->renderer->failWith(new QuotaExhausted('No more today', new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'))));

        $this->artisan('social-images:generate')
            ->expectsOutputToContain('allowance has run out')
            ->doesntExpectOutputToContain('Rate limited')
            ->assertFailed();

        $this->assertCount(0, $this->renderer->requests);
    }

    public function test_the_command_waits_as_long_as_the_service_asks(): void
    {
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry('a');
        $this->renderer->failWith(new RateLimited('Slow down', retryAfter: 0));

        $this->artisan('social-images:generate')->expectsOutputToContain('trying again in 0s')->assertSuccessful();

        $this->assertCount(1, $this->renderer->requests);
    }

    public function test_the_command_shows_the_fix(): void
    {
        $this->template();
        $this->settings(['templates' => [['template' => 'basic']], 'generate_on_save' => false]);
        $this->entry('a');
        $this->renderer->failWith(new NotConfigured('No Chrome', 'Install Chrome.'));

        $this->artisan('social-images:generate')->expectsOutputToContain('Install Chrome')->assertFailed();
    }

    public function test_regenerating_says_when_the_allowance_is_gone(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->entry();
        $this->renderer->failWith(new QuotaExhausted('No more today', new DateTimeImmutable('2026-10-18T00:00:00Z')));

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.images.regenerate', 'one'))
            ->assertStatus(429)
            ->assertJsonPath('rate_limited', true)
            ->assertJsonPath('quota', true)
            ->assertJsonPath('resets_at', '2026-10-18T00:00:00+00:00');
    }

    public function test_regenerating_passes_on_the_wait(): void
    {
        $this->template();
        $this->useTemplate('basic');
        $this->entry();
        $this->renderer->failWith(new RateLimited('Slow down', retryAfter: 30));

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.images.regenerate', 'one'))
            ->assertStatus(429)
            ->assertJsonPath('quota', false)
            ->assertJsonPath('retry_after', 30);
    }

    public function test_the_doctor_reports_a_working_renderer(): void
    {
        $this->artisan('social-images:doctor')
            ->expectsOutputToContain('fake')
            ->expectsOutputToContain('Rendered a test image')
            ->assertSuccessful();
    }

    public function test_the_doctor_says_what_to_fix(): void
    {
        $this->renderer->failWith(new NotConfigured('No Chrome', 'Install Chrome.'));

        $this->artisan('social-images:doctor')->expectsOutputToContain('No Chrome')->expectsOutputToContain('Install Chrome')->assertFailed();
    }

    public function test_the_doctor_reports_an_unknown_driver(): void
    {
        $this->artisan('social-images:doctor', ['--driver' => 'nope'])
            ->expectsOutputToContain('There’s no renderer called “nope”')
            ->assertFailed();
    }

    public function test_the_settings_test_button(): void
    {
        $this->actingAs($this->cpUser(['view social images']))->postJson(cp_route('social-images.renderer.check'))->assertForbidden();

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.renderer.check'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('driver', 'fake');

        $this->renderer->failWith(new RenderFailed('Broken'));

        $this->actingAs($this->cpUser())->postJson(cp_route('social-images.renderer.check'))
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Broken');
    }

    public function test_laravel_screenshot_errors_become_typed(): void
    {
        $renderer = new class extends LaravelScreenshotRenderer
        {
            public function map(string $message): \Throwable
            {
                return $this->failure(new RuntimeException($message));
            }
        };

        $this->assertInstanceOf(QuotaExhausted::class, $renderer->map('Cloudflare screenshot generation failed: {"errors":[{"code":429,"message":"Browser time limit exceeded for today"}]}'));
        $this->assertInstanceOf(RateLimited::class, $renderer->map('Cloudflare screenshot generation failed: 429'));
        $this->assertInstanceOf(NotConfigured::class, $renderer->map('The spatie/browsershot package is required to use the Browsershot driver.'));
        $this->assertInstanceOf(RenderFailed::class, $renderer->map('Something else'));
    }
}
