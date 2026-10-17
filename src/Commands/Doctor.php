<?php

namespace NineteenNinetyFour\SocialImages\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\SocialImages\Core\Rendering\RendererManager;
use NineteenNinetyFour\SocialImages\Rendering\Diagnose;
use Statamic\Console\RunsInPlease;

/**
 * Renders a test image with the configured renderer (or another, with
 * --driver) and says whether it worked, and if not, what to change.
 */
class Doctor extends Command
{
    use RunsInPlease;

    protected $signature = 'social-images:doctor
        {--driver= : Check this renderer instead of the one in use}';

    protected $description = 'Check social images can be rendered';

    public function handle(RendererManager $renderers): int
    {
        $driver = $this->option('driver') ?: $renderers->defaultDriver();
        $result = Diagnose::driver($driver);

        $this->components->twoColumnDetail('Renderer', $driver);

        if ($result['capabilities']) {
            $this->components->twoColumnDetail('Runs', $result['capabilities']['remote'] ? 'on another service (page HTML is sent to it)' : 'on this server');
            $this->components->twoColumnDetail('Formats', implode(', ', $result['capabilities']['formats']));
        }

        $this->components->twoColumnDetail('Available', implode(', ', $renderers->names()));
        $this->newLine();

        if ($result['ok']) {
            $this->components->info($result['message']);

            return self::SUCCESS;
        }

        $this->components->error($result['message']);

        if ($result['fix']) {
            $this->components->bulletList([$result['fix']]);
        }

        return self::FAILURE;
    }
}
