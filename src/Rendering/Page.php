<?php

namespace NineteenNinetyFour\SocialImages\Rendering;

/**
 * The HTML pages screenshots are taken of.
 *
 * Both kinds carry the renderer (resources/runtime/runtime.js) inline, and
 * set html[data-social-ready] when they're done, which the screenshot
 * waits for. Neither loads anything over the network.
 */
class Page
{
    /**
     * Part of every image's fingerprint. Bump it when a change to the
     * renderer changes how existing templates look, so they re-render.
     */
    public const RUNTIME_VERSION = 1;

    protected ?string $runtime = null;

    /**
     * A designer template's page: the layout and its resolved data, drawn
     * by the renderer.
     */
    public function designer(array $layout, array $data, string $lang = 'en'): string
    {
        $payload = json_encode(['layout' => $layout, 'data' => $data], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return '<!doctype html>'
            .'<html lang="'.e($lang).'"><head><meta charset="utf-8"><title>Social image</title>'
            .$this->baseStyle($layout['width'], $layout['height'])
            .'</head><body><div id="social-image"></div>'
            .'<script>'.$this->runtime()
            .'(function(){var p='.$payload.';'
            .'SocialImages.render(document.getElementById("social-image"),p.layout,p.data)'
            .'.catch(function(e){console.error(e)})'
            .'.then(function(){document.documentElement.setAttribute("data-social-ready","")});})();'
            .'</script></body></html>';
    }

    /**
     * A code template's rendered view, with the renderer added so its
     * [data-fit] text is fitted and the page marked ready.
     */
    public function view(string $html): string
    {
        $script = '<script>'.$this->runtime().'SocialImages.ready();</script>';

        return str_contains($html, '</body>')
            ? substr_replace($html, $script.'</body>', strrpos($html, '</body>'), strlen('</body>'))
            : $html.$script;
    }

    /**
     * The renderer as a plain script defining window.SocialImages.
     */
    public function runtime(): string
    {
        if ($this->runtime !== null) {
            return $this->runtime;
        }

        $source = (string) file_get_contents(__DIR__.'/../../resources/runtime/runtime.js');
        $source = preg_replace('/^export\s+/m', '', $source);

        return $this->runtime = '(function(){'.$source.'window.SocialImages={render:render,ready:ready,fitText:fitText,loadFonts:loadFonts};})();';
    }

    protected function baseStyle(int $width, int $height): string
    {
        return '<style>html,body{margin:0;padding:0}'
            ."body{width:{$width}px;height:{$height}px;overflow:hidden;-webkit-font-smoothing:antialiased;text-rendering:geometricPrecision}"
            .'</style>';
    }
}
