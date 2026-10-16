<?php

namespace NineteenNinetyFour\SocialImages\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\Library\GoogleFonts;
use NineteenNinetyFour\SocialImages\Library\Uploads;
use Statamic\Http\Controllers\CP\CpController;

/**
 * The designer's fonts and uploaded images.
 */
class LibraryController extends CpController
{
    public function fonts(Fonts $fonts): JsonResponse
    {
        $this->authorize('manage social image templates');

        return response()->json(['fonts' => $fonts->families()]);
    }

    public function google(Request $request, GoogleFonts $google, Fonts $fonts): JsonResponse
    {
        $this->authorize('manage social image templates');

        $request->validate([
            'family' => ['required', 'string', 'max:60'],
            'weights' => ['array'],
            'weights.*' => ['integer'],
            'italic' => ['boolean'],
        ]);

        try {
            $google->add($request->input('family'), $request->input('weights', [400, 700]), $request->boolean('italic'));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['fonts' => app(Fonts::class)->families()]);
    }

    public function uploadFont(Request $request, Fonts $fonts): JsonResponse
    {
        $this->authorize('manage social image templates');

        $request->validate([
            'family' => ['required', 'string', 'max:60'],
            'file' => ['required', 'file', 'max:5120'],
            'weight' => ['nullable', 'integer'],
            'style' => ['nullable', 'in:normal,italic'],
        ]);

        $file = $request->file('file');

        try {
            $fonts->add(
                $request->input('family'),
                (string) $file->get(),
                strtolower($file->getClientOriginalExtension()),
                (int) $request->input('weight', 400),
                $request->input('style', 'normal'),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['fonts' => app(Fonts::class)->families()]);
    }

    public function deleteFont(Request $request, Fonts $fonts): JsonResponse
    {
        $this->authorize('manage social image templates');

        $fonts->delete((string) $request->input('family'));

        return response()->json(['fonts' => $fonts->families()]);
    }

    public function upload(Request $request, Uploads $uploads): JsonResponse
    {
        $this->authorize('manage social image templates');

        $request->validate(['file' => ['required', 'file', 'max:5120']]);

        $file = $request->file('file');

        try {
            $name = $uploads->put((string) $file->get(), strtolower($file->getClientOriginalExtension()));
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['source' => 'upload:'.$name, 'url' => cp_route('social-images.uploads.show', $name)]);
    }

    /**
     * An uploaded image, for the designer. Sandboxed, so an SVG can't run
     * script if opened directly.
     */
    public function show(string $name, Uploads $uploads)
    {
        $this->authorize('view social images');

        abort_unless($uploads->exists($name), 404);

        return response($uploads->get($name))->withHeaders([
            'Content-Type' => $uploads->mime($name),
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
