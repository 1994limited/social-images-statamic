<?php

namespace NineteenNinetyFour\SocialImages\Rendering;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Query\Builder;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Bard;
use Statamic\Statamic;
use Statamic\Support\Str;
use Stringable;

/**
 * Fills in the {tokens} in a text layer from an entry.
 *
 *   {title}                 the entry's title
 *   {subtitle}              any field, by handle ({field:subtitle} also works)
 *   {author:name}           a field of a related entry, term or user
 *   {collection:title}      the collection's title (or :handle)
 *   {site:name}             the site's name (or :url, :locale)
 *   {date} {date:j M Y}     the entry's date, in a PHP date format
 *   {url} {domain} {path}   where the entry lives
 *   {subtitle ?? excerpt ?? "Read more"}   the first that has a value
 *
 * Everything comes out as plain text: HTML is stripped, entities decoded and
 * white space squashed. A backslash keeps a brace as it is: \{.
 */
class Tokens
{
    /**
     * What the designer shows for a token when no entry is picked.
     */
    public const SAMPLES = [
        'title' => 'Your entry’s title shows here',
        'site:name' => 'Your site',
        'collection:title' => 'Articles',
        'author:name' => 'Ada Lovelace',
        'date' => '16 October 2026',
        'domain' => 'example.com',
    ];

    public function text(string $text, ?Entry $entry): string
    {
        $text = preg_replace_callback('/(?<!\\\\)\{([^{}\n]{1,200})\}/u', fn ($match) => $this->evaluate($match[1], $entry), $text);

        return str_replace(['\\{', '\\}'], ['{', '}'], (string) $text);
    }

    /**
     * Whether an expression has a value, for a layer's "show only when".
     */
    public function filled(?string $expression, ?Entry $entry): bool
    {
        if ($expression === null || trim($expression) === '') {
            return true;
        }

        $expression = trim($expression, " \t{}");

        return trim($this->evaluate($expression, $entry)) !== '';
    }

    public function evaluate(string $expression, ?Entry $entry): string
    {
        foreach (preg_split('/\s*\?\?\s*/', trim($expression)) as $part) {
            if (preg_match('/^(["\'])(.*)\1$/su', $part, $literal)) {
                return $literal[2];
            }

            $value = $entry ? $this->token($part, $entry) : $this->sample($part);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Every field handle an expression or text reads, so a fingerprint can
     * include just those.
     *
     * @return array<int, string>
     */
    public function references(string $text): array
    {
        preg_match_all('/(?<!\\\\)\{([^{}\n]{1,200})\}/u', $text, $matches);

        return collect($matches[1])
            ->flatMap(fn ($expression) => preg_split('/\s*\?\?\s*/', trim($expression)))
            ->reject(fn ($part) => preg_match('/^(["\']).*\1$/su', $part))
            ->values()
            ->all();
    }

    protected function token(string $token, Entry $entry): string
    {
        [$name, $argument] = array_pad(explode(':', $token, 2), 2, null);
        $name = trim($name);

        return match ($name) {
            'site' => $this->site($entry, $argument ?? 'name'),
            'collection' => $argument === 'handle' ? (string) $entry->collectionHandle() : (string) $entry->collection()?->title(),
            'date' => $this->date($entry, $argument),
            'url' => (string) $entry->absoluteUrl(),
            'domain' => (string) parse_url((string) $entry->absoluteUrl(), PHP_URL_HOST),
            'path' => (string) $entry->url(),
            'field' => $argument ? $this->field($entry, $argument) : '',
            default => $this->field($entry, $argument !== null ? $name.'.'.$argument : $name),
        };
    }

    /**
     * A field by handle, or a field of the first thing a relationship field
     * points at ("author.name"), as plain text.
     */
    public function field(Entry|Term|User|Augmentable $item, string $handle): string
    {
        [$handle, $rest] = array_pad(explode('.', str_replace(':', '.', trim($handle)), 2), 2, null);

        if (! preg_match('/^[a-zA-Z0-9_]+$/', $handle)) {
            return '';
        }

        if ($handle === 'author' && $item instanceof Entry && $rest === null) {
            $rest = 'name';
        }

        $value = $item->augmentedValue($handle);

        if ($rest !== null) {
            $related = $this->first($value instanceof Value ? $value->value() : $value);

            return $related instanceof Augmentable ? $this->field($related, $rest) : '';
        }

        return $this->plain($value);
    }

    public function plain(mixed $value): string
    {
        if ($value instanceof Value) {
            if ($value->fieldtype() instanceof Bard) {
                return $this->squish((string) Statamic::modify($value->raw())->bardText());
            }

            $value = $value->value();
        }

        // Relationship fields augment to a query.
        if ($value instanceof Builder) {
            $value = $value->get();
        }

        return $this->squish(match (true) {
            $value === null, is_bool($value) => '',
            $value instanceof LabeledValue => (string) $value->label(),
            $value instanceof CarbonInterface => $value->format('j F Y'),
            $value instanceof Asset => (string) ($value->get('alt') ?: $value->title()),
            $value instanceof User => (string) $value->name(),
            $value instanceof Entry, $value instanceof Term => (string) $value->get('title') ?: (string) $value->value('title'),
            $value instanceof Collection, is_iterable($value) => collect($value)->map(fn ($item) => $this->plain($item))->filter()->implode(', '),
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        });
    }

    protected function first(mixed $value): mixed
    {
        if ($value instanceof Collection || is_array($value)) {
            return collect($value)->first();
        }

        if (is_object($value) && method_exists($value, 'get') && method_exists($value, 'first')) {
            return $value->get()->first();
        }

        return $value;
    }

    protected function site(Entry $entry, string $part): string
    {
        $site = $entry->site();

        return match ($part) {
            'url' => (string) $site->absoluteUrl(),
            'locale' => (string) $site->locale(),
            'handle' => (string) $site->handle(),
            default => (string) $site->name(),
        };
    }

    protected function date(Entry $entry, ?string $format): string
    {
        $date = $entry->hasDate() ? $entry->date() : $entry->lastModified();

        return $date ? $date->translatedFormat($format ?: 'j F Y') : '';
    }

    protected function sample(string $token): string
    {
        $token = trim($token);

        if (isset(self::SAMPLES[$token])) {
            return self::SAMPLES[$token];
        }

        if (str_starts_with($token, 'date')) {
            return self::SAMPLES['date'];
        }

        if (in_array($token, ['url', 'path'], true)) {
            return 'https://example.com/your-entry';
        }

        // Any other field: its name, so the layer has something to show.
        return Str::headline(str_replace([':', '.', 'field '], ' ', $token));
    }

    protected function squish(string $text): string
    {
        return trim(Str::squish(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
