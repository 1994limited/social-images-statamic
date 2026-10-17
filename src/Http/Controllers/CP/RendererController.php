<?php

namespace NineteenNinetyFour\SocialImages\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use NineteenNinetyFour\SocialImages\Rendering\Diagnose;
use Statamic\Http\Controllers\CP\CpController;

class RendererController extends CpController
{
    /**
     * The settings page's Test button: one test render with the renderer
     * in use.
     */
    public function check(): JsonResponse
    {
        // A render spends the screenshot service's allowance.
        $this->authorize('manage social image templates');

        return response()->json(Diagnose::driver());
    }
}
