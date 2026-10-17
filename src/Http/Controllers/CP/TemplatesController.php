<?php

namespace NineteenNinetyFour\SocialImages\Http\Controllers\CP;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\SocialImages\Images\Camera;
use NineteenNinetyFour\SocialImages\Images\Cards;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\RateLimits;
use NineteenNinetyFour\SocialImages\Rendering\Page;
use NineteenNinetyFour\SocialImages\Rendering\Resolver;
use NineteenNinetyFour\SocialImages\Rendering\Tokens;
use NineteenNinetyFour\SocialImages\Settings;
use NineteenNinetyFour\SocialImages\Support\Catalog;
use NineteenNinetyFour\SocialImages\Support\InSite;
use NineteenNinetyFour\SocialImages\Templates\InvalidTemplate;
use NineteenNinetyFour\SocialImages\Templates\JsonTemplate;
use NineteenNinetyFour\SocialImages\Templates\Porter;
use NineteenNinetyFour\SocialImages\Templates\Schema;
use NineteenNinetyFour\SocialImages\Templates\Template;
use NineteenNinetyFour\SocialImages\Templates\TemplateRepository;
use NineteenNinetyFour\SocialImages\Templates\ViewTemplate;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tools → Social Images: the templates, the designer, and importing and
 * exporting.
 */
class TemplatesController extends CpController
{
    public function __construct(
        Request $request,
        protected TemplateRepository $templates,
        protected Porter $porter,
        protected Catalog $catalog,
    ) {
        parent::__construct($request);
    }

    public function index(): Response
    {
        $this->authorize('view social images');

        $rules = collect(app(Settings::class)->rules());

        return Inertia::render('social-images::Templates', [
            'templates' => $this->templates->all()->map(fn (Template $template) => [
                'handle' => $template->handle(),
                'title' => $template->title(),
                'kind' => $template->kind(),
                'view' => $template instanceof ViewTemplate ? $template->view() : null,
                'layouts' => $template->layouts(),
                'layout' => $template instanceof JsonTemplate ? $template->layout(Schema::BASE_LAYOUT) : null,
                'used_by' => $rules->where('template', $template->handle())->count(),
                'edit_url' => $template instanceof JsonTemplate ? cp_route('social-images.templates.edit', $template->handle()) : null,
                'export_url' => $template instanceof JsonTemplate ? cp_route('social-images.templates.export', $template->handle()) : null,
                'delete_url' => $template instanceof JsonTemplate ? cp_route('social-images.templates.destroy', $template->handle()) : null,
                'duplicate_url' => $template instanceof JsonTemplate ? cp_route('social-images.templates.duplicate', $template->handle()) : null,
                'preview_url' => cp_route('social-images.templates.thumbnail', $template->handle()),
            ])->values()->all(),
            'starters' => collect($this->templates->starters())->map(fn ($data, $key) => [
                'key' => $key,
                'title' => $data['title'] ?? $key,
                'description' => $data['description'] ?? '',
                'layout' => $data['layouts'][Schema::BASE_LAYOUT] ?? null,
            ])->values()->all(),
            'canManage' => User::current()->can('manage social image templates'),
            'urls' => [
                'store' => cp_route('social-images.templates.store'),
                'import' => cp_route('social-images.templates.import'),
                'images' => cp_route('social-images.images.index'),
                'settings' => cp_route('addons.settings.edit', 'social-images-statamic'),
                'preview' => cp_route('social-images.preview'),
            ],
            'gallery' => 'https://github.com/1994limited/social-images-statamic/tree/main/gallery',
        ]);
    }

    /**
     * A new template: blank, from a starter, or a copy.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage social image templates');

        $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'starter' => ['nullable', 'string'],
        ]);

        $data = $request->input('starter')
            ? $this->templates->starters()[$request->input('starter')] ?? abort(404)
            : ['schema' => Schema::VERSION, 'layouts' => [Schema::BASE_LAYOUT => ['width' => 1200, 'height' => 630, 'layers' => []]]];

        $data['title'] = $request->input('title') ?: ($data['title'] ?? __('New template'));

        $template = $this->templates->save($this->templates->uniqueHandle($data['title']), $data);

        return response()->json(['redirect' => cp_route('social-images.templates.edit', $template->handle())]);
    }

    public function duplicate(string $handle): JsonResponse
    {
        $this->authorize('manage social image templates');

        $template = $this->designerTemplate($handle);
        $data = $template->toArray();
        $data['title'] = __(':title (copy)', ['title' => $template->title()]);

        $copy = $this->templates->save($this->templates->uniqueHandle($data['title']), $data);

        return response()->json(['redirect' => cp_route('social-images.templates.edit', $copy->handle())]);
    }

    /**
     * Import from pasted JSON, an uploaded file or a URL.
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('manage social image templates');

        $request->validate([
            'json' => ['nullable', 'string', 'max:20000000'],
            'file' => ['nullable', 'file', 'max:'.(int) config('social-images.import.max_kilobytes', 10240)],
            'url' => ['nullable', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $data = match (true) {
                $request->hasFile('file') => $this->porter->decode((string) $request->file('file')->get()),
                filled($request->input('url')) => $this->porter->fetch($request->input('url')),
                filled($request->input('json')) => $this->porter->decode($request->input('json')),
                default => throw new InvalidTemplate(__('Paste a template, choose a file or give its address.')),
            };

            $template = $this->porter->import($data, $request->input('title'));
        } catch (InvalidTemplate $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        session()->flash('success', __('Template imported'));

        return response()->json(['redirect' => cp_route('social-images.templates.edit', $template->handle())]);
    }

    public function edit(string $handle): Response
    {
        $this->authorize('manage social image templates');

        $template = $this->designerTemplate($handle);

        return Inertia::render('social-images::Designer', [
            'template' => ['handle' => $template->handle(), ...$template->toArray()],
            'fonts' => app(Fonts::class)->families(),
            'presets' => Schema::PRESETS,
            'collections' => $this->catalog->collections(),
            'entries' => $this->catalog->entries(user: User::current()),
            'samples' => Tokens::SAMPLES,
            'urls' => [
                'index' => cp_route('social-images.index'),
                'update' => cp_route('social-images.templates.update', $template->handle()),
                'export' => cp_route('social-images.templates.export', $template->handle()),
                'preview' => cp_route('social-images.preview'),
                'render' => cp_route('social-images.render'),
                'entries' => cp_route('social-images.entries'),
                'fonts' => cp_route('social-images.fonts.index'),
                'google' => cp_route('social-images.fonts.google'),
                'font_upload' => cp_route('social-images.fonts.upload'),
                'upload' => cp_route('social-images.uploads.store'),
                'upload_show' => cp_route('social-images.uploads.show', '__NAME__'),
            ],
        ]);
    }

    public function update(Request $request, string $handle): JsonResponse
    {
        $this->authorize('manage social image templates');

        $this->designerTemplate($handle);

        try {
            $template = $this->templates->save($handle, (array) $request->input('template'));
        } catch (InvalidTemplate $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['template' => ['handle' => $template->handle(), ...$template->toArray()]]);
    }

    public function destroy(string $handle): JsonResponse
    {
        $this->authorize('manage social image templates');

        $this->designerTemplate($handle);
        $this->templates->delete($handle);

        return response()->json(['deleted' => true]);
    }

    public function export(string $handle): StreamedResponse
    {
        $this->authorize('manage social image templates');

        $template = $this->designerTemplate($handle);
        $json = json_encode($this->porter->export($template), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(fn () => print ($json), $handle.'.social-image.json', ['Content-Type' => 'application/json']);
    }

    /**
     * What the renderer needs to draw an unsaved layout, for the designer's
     * canvas: each layer's text or picture for the chosen entry (or sample
     * text), and the fonts.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorize('view social images');

        $layout = Schema::layout((array) $request->input('layout'));
        $entry = $this->entry($request->input('entry'));

        return response()->json([
            'layout' => $layout,
            'data' => InSite::run($entry, fn () => app(Resolver::class)->resolve($layout, $entry)),
        ]);
    }

    /**
     * A real render, through the screenshot service: of an unsaved designer
     * layout, or of a saved template (code templates have no canvas).
     */
    public function render(Request $request): JsonResponse
    {
        // Renders spend the screenshot service's allowance.
        $this->authorize('manage social image templates');

        $entry = $this->entry($request->input('entry'));

        try {
            if ($request->filled('template')) {
                $template = $this->templates->find($request->input('template')) ?? abort(404);
                $layoutHandle = $template->hasLayout((string) $request->input('layout_handle')) ? $request->input('layout_handle') : Schema::BASE_LAYOUT;
                $size = $template->layouts()[$layoutHandle];
                $html = $template->html($entry, $layoutHandle);
            } else {
                $layout = Schema::layout((array) $request->input('layout'));
                $size = ['width' => $layout['width'], 'height' => $layout['height']];
                $html = app(Page::class)->designer($layout, InSite::run($entry, fn () => app(Resolver::class)->resolve($layout, $entry)));
            }

            $png = app(Camera::class)->capture($html, $size['width'], $size['height']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => __('The image couldn’t be rendered: :message', ['message' => str($exception->getMessage())->limit(300)]),
                'fix' => RateLimits::fix($exception),
            ], RateLimits::caused($exception) ? 429 : 500);
        }

        return response()->json(['image' => 'data:image/png;base64,'.base64_encode($png)]);
    }

    /**
     * A saved template's page with sample text, for the index's thumbnails.
     * Served as HTML into a sandboxed frame.
     */
    public function thumbnail(Request $request, string $handle)
    {
        $this->authorize('view social images');

        $template = $this->templates->find($handle) ?? abort(404);
        $entry = $this->entry($request->query('entry'))
            ?? ($template instanceof ViewTemplate ? $this->exampleEntry($template) : null);

        try {
            $html = $template->html($entry, Schema::BASE_LAYOUT);
        } catch (Throwable $exception) {
            report($exception);
            $html = '<p style="font:14px system-ui;padding:20px">'.e(__('This template couldn’t be shown: :message', ['message' => $exception->getMessage()])).'</p>';
        }

        return response($html)->withHeaders([
            // Sandboxed even when opened directly: a code template shows
            // entry values unescaped, so its script must not run as the CP.
            'Content-Security-Policy' => "sandbox allow-scripts; default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'; script-src 'unsafe-inline'",
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /**
     * A recent entry that uses the template, which the user can see, to
     * preview a code template with. A view has nothing to show but the
     * title without one, while a designer template has sample text.
     */
    protected function exampleEntry(Template $template): ?EntryContract
    {
        $cards = app(Cards::class);
        $user = User::current();

        return Entry::query()->orderBy('date', 'desc')->limit(200)->get()
            ->first(fn (EntryContract $entry) => $cards->supports($entry)
                && $cards->templateFor($entry)?->handle() === $template->handle()
                && $user->can('view', $entry));
    }

    public function entries(Request $request): JsonResponse
    {
        $this->authorize('view social images');

        return response()->json(['entries' => $this->catalog->entries($request->query('search'), $request->query('collection'), User::current())]);
    }

    /**
     * The entry to preview with, if the user may see it: its values are
     * shown, so they're as private as the entry.
     */
    protected function entry(mixed $id): ?EntryContract
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        $entry = Entry::find($id);

        abort_unless($entry && User::current()->can('view', $entry), 404);

        return $entry;
    }

    protected function designerTemplate(string $handle): JsonTemplate
    {
        $template = $this->templates->find($handle);

        abort_unless($template instanceof JsonTemplate, 404);

        return $template;
    }
}
