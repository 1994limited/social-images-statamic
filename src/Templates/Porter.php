<?php

namespace NineteenNinetyFour\SocialImages\Templates;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use NineteenNinetyFour\SocialImages\Library\Fonts;
use NineteenNinetyFour\SocialImages\Library\Uploads;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Asset as AssetFacade;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Exports templates as one self-contained JSON file, and imports them from
 * one: pasted, uploaded or fetched from a URL.
 *
 * An export carries the template plus everything it needs that another
 * site won't have: its uploaded images and site assets (as data URIs, the
 * assets turned into uploads) and any fonts that aren't bundled. Importing
 * stores those, then saves the template through Schema::normalize() like
 * any other, so nothing but layout data gets in.
 */
class Porter
{
    public function __construct(
        protected TemplateRepository $templates,
        protected Uploads $uploads,
        protected Fonts $fonts,
    ) {}

    public function export(JsonTemplate $template): array
    {
        $data = $template->toArray();
        $uploads = [];

        foreach ($data['layouts'] as $handle => $layout) {
            foreach ($layout['layers'] as $index => $layer) {
                if ($layer['type'] !== 'image') {
                    continue;
                }

                $data['layouts'][$handle]['layers'][$index]['source'] = collect($layer['source'])
                    ->map(function (string $source) use (&$uploads) {
                        if (str_starts_with($source, 'upload:') && $uri = $this->uploads->dataUri($name = substr($source, 7))) {
                            $uploads[$name] = $uri;
                        }

                        if (str_starts_with($source, 'asset:') && $upload = $this->assetAsUpload(substr($source, 6))) {
                            [$name, $uri] = $upload;
                            $uploads[$name] = $uri;

                            return 'upload:'.$name;
                        }

                        return $source;
                    })
                    ->all();
            }
        }

        return [
            ...$data,
            'fonts' => collect($template->families())
                ->reject(fn ($family) => $this->fonts->isBundled($family))
                ->flatMap(fn ($family) => $this->fonts->export($family))
                ->values()
                ->all(),
            'uploads' => $uploads,
        ];
    }

    /**
     * Import an exported template as a new one. A handle is made from its
     * title (or the one given) if that's free.
     *
     * @throws InvalidTemplate
     */
    public function import(array $data, ?string $title = null): JsonTemplate
    {
        // Check the template before storing anything it brings.
        Schema::normalize($data);

        $fonts = is_array($data['fonts'] ?? null) ? $data['fonts'] : [];
        $images = [];

        // Check every image and font first, so a template that fails
        // part-way through leaves nothing behind.
        try {
            foreach (is_array($data['uploads'] ?? null) ? $data['uploads'] : [] as $name => $uri) {
                if (! is_string($uri) || ! preg_match('#^data:image/(png|jpeg|webp|gif|svg\+xml|avif);base64,([A-Za-z0-9+/=\s]+)$#', $uri, $matches)) {
                    throw new InvalidTemplate(__('An image in this template isn’t one Social Images can use.'));
                }

                $contents = base64_decode($matches[2]);
                $extension = $this->uploads->check($contents, ['jpeg' => 'jpg', 'svg+xml' => 'svg'][$matches[1]] ?? $matches[1]);
                $images[(string) $name] = [$contents, $extension];
            }

            $this->fonts->checkImport($fonts);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidTemplate($exception->getMessage());
        }

        $renamed = [];

        foreach ($images as $name => [$contents, $extension]) {
            $renamed[$name] = $this->uploads->put($contents, $extension);
        }

        $this->fonts->import($fonts);

        // Uploads are named by their contents, so the names should match,
        // but a hand-edited file may not.
        foreach ($data['layouts'] as $handle => $layout) {
            foreach (is_array($layout['layers'] ?? null) ? $layout['layers'] : [] as $index => $layer) {
                if (is_array($layer) && isset($layer['source'])) {
                    $data['layouts'][$handle]['layers'][$index]['source'] = collect((array) $layer['source'])
                        ->map(fn ($source) => is_string($source) && str_starts_with($source, 'upload:') && isset($renamed[substr($source, 7)])
                            ? 'upload:'.$renamed[substr($source, 7)]
                            : $source)
                        ->all();
                }
            }
        }

        $data['title'] = $title ?: ($data['title'] ?? null);
        $handle = $this->templates->uniqueHandle((string) ($data['title'] ?: 'template'));

        return $this->templates->save($handle, collect($data)->except(['fonts', 'uploads'])->all());
    }

    /**
     * Parse pasted or uploaded JSON.
     *
     * @throws InvalidTemplate
     */
    public function decode(string $json): array
    {
        $data = json_decode(trim($json), true);

        if (! is_array($data)) {
            throw new InvalidTemplate(__('That isn’t a Social Images template: it isn’t valid JSON.'));
        }

        if (! isset($data['schema']) || ! isset($data['layouts'])) {
            throw new InvalidTemplate(__('That JSON isn’t a Social Images template.'));
        }

        return $data;
    }

    /**
     * Fetch a template from a URL. Only https on a public address, with no
     * redirects, and no more than the configured size. A GitHub file page
     * is fetched from its raw address.
     *
     * @throws InvalidTemplate
     */
    public function fetch(string $url): array
    {
        $url = $this->rawGitHubUrl(trim($url));
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidTemplate(__('Templates can only be imported from https:// addresses.'));
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? 443;

        if ($port !== 443) {
            throw new InvalidTemplate(__('Templates can only be imported from https:// addresses.'));
        }
        $ip = $this->publicAddress($host);

        $limit = max(1, (int) config('social-images.import.max_kilobytes', 10240)) * 1024;

        try {
            $response = Http::timeout((int) config('social-images.import.timeout', 15))
                ->withOptions([
                    'allow_redirects' => false,
                    // Connect to the address that was checked, not whatever
                    // the name resolves to a moment later.
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                    'progress' => function ($total, $downloaded) use ($limit) {
                        if ($total > $limit || $downloaded > $limit) {
                            throw new InvalidArgumentException('too large');
                        }
                    },
                ])
                ->accept('application/json, text/plain')
                ->get($url);
        } catch (Throwable $exception) {
            throw new InvalidTemplate(str_contains($exception->getMessage(), 'too large')
                ? __('That template is larger than the import limit.')
                : __('That address couldn’t be reached.'));
        }

        if (! $response->successful()) {
            throw new InvalidTemplate(__('That address returned an error (:status).', ['status' => $response->status()]));
        }

        if (strlen($response->body()) > $limit) {
            throw new InvalidTemplate(__('That template is larger than the import limit.'));
        }

        return $this->decode($response->body());
    }

    /**
     * The host's address, if every address it has is public.
     *
     * @throws InvalidTemplate
     */
    protected function publicAddress(string $host): string
    {
        $host = trim($host, '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        $public = fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! $this->internal($ip);

        if (! $addresses || collect($addresses)->contains(fn ($ip) => ! $public($ip))) {
            throw new InvalidTemplate(__('Templates can only be imported from public addresses.'));
        }

        return $addresses[0];
    }

    /**
     * Ranges PHP's private and reserved flags let through but that reach
     * inside a network: carrier-grade NAT (where some clouds keep their
     * metadata service), benchmarking, and IPv6 addresses that carry an
     * IPv4 one (NAT64, 6to4, mapped).
     */
    protected const INTERNAL = [
        '100.64.0.0/10',
        '198.18.0.0/15',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '2002::/16',
        '::ffff:0:0/96',
    ];

    protected function internal(string $ip): bool
    {
        return IpUtils::checkIp($ip, self::INTERNAL);
    }

    /**
     * @return array<int, string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return collect($records)
            ->map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    protected function rawGitHubUrl(string $url): string
    {
        if (preg_match('#^https://github\.com/([^/]+)/([^/]+)/blob/(.+)$#', $url, $matches)) {
            return "https://raw.githubusercontent.com/{$matches[1]}/{$matches[2]}/{$matches[3]}";
        }

        return $url;
    }

    /**
     * @return array{0: string, 1: string}|null the upload's name and data URI
     */
    protected function assetAsUpload(string $id): ?array
    {
        $asset = AssetFacade::find($id);

        if (! $asset instanceof Asset) {
            return null;
        }

        $contents = rescue(fn () => $asset->disk()->get($asset->path()), null, false);

        if (! $contents) {
            return null;
        }

        $extension = strtolower((string) $asset->extension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        if (! isset(Uploads::TYPES[$extension])) {
            return null;
        }

        return [sha1($contents).'.'.$extension, 'data:'.Uploads::TYPES[$extension].';base64,'.base64_encode($contents)];
    }
}
