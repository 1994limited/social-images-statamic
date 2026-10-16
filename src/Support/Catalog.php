<?php

namespace NineteenNinetyFour\SocialImages\Support;

use NineteenNinetyFour\SocialImages\Images\Cards;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site;

/**
 * What the designer offers to bind layers to: each routable collection's
 * fields, and entries to preview with.
 */
class Catalog
{
    /** Fieldtypes whose value reads as text. */
    protected const TEXT = ['text', 'textarea', 'markdown', 'bard', 'slug', 'select', 'radio', 'button_group', 'integer', 'float', 'date', 'entries', 'terms', 'users', 'checkboxes', 'toggle', 'color', 'link'];

    /** Fieldtypes that can hold a picture, directly or through another item. */
    protected const IMAGE = ['assets'];

    protected const RELATED = ['entries', 'users', 'terms'];

    /**
     * @return array<int, array{handle: string, title: string, fields: array<int, array{handle: string, display: string, type: string, kind: string}>}>
     */
    public function collections(): array
    {
        return Collection::all()
            ->filter(fn ($collection) => $collection->route(Site::default()->handle()) !== null || $collection->routes()->filter()->isNotEmpty())
            ->sortBy(fn ($collection) => $collection->title())
            ->map(fn ($collection) => [
                'handle' => $collection->handle(),
                'title' => $collection->title(),
                'fields' => $collection->entryBlueprints()
                    ->flatMap(fn ($blueprint) => $blueprint->fields()->all())
                    ->reject(fn ($field) => in_array($field->handle(), [Cards::OVERRIDE_FIELD, Cards::TEMPLATE_FIELD], true))
                    ->map(fn ($field) => [
                        'handle' => $field->handle(),
                        'display' => (string) $field->display(),
                        'type' => $field->type(),
                        'kind' => match (true) {
                            in_array($field->type(), self::IMAGE, true) => 'image',
                            in_array($field->type(), self::RELATED, true) => 'related',
                            in_array($field->type(), self::TEXT, true) => 'text',
                            default => 'other',
                        },
                    ])
                    ->reject(fn ($field) => $field['kind'] === 'other')
                    ->unique('handle')
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Entries to preview with, newest first.
     *
     * @return array<int, array{id: string, title: string, collection: string, site: string}>
     */
    public function entries(?string $search = null, ?string $collection = null, int $limit = 20): array
    {
        $query = Entries::query()->whereIn('collection', collect($this->collections())->pluck('handle')->all());

        if ($collection) {
            $query->where('collection', $collection);
        }

        if ($search) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        return $query->orderBy('date', 'desc')->limit($limit)->get()
            ->map(fn (Entry $entry) => $this->entry($entry))
            ->values()
            ->all();
    }

    public function entry(Entry $entry): array
    {
        return [
            'id' => $entry->id(),
            'title' => (string) $entry->get('title'),
            'collection' => (string) $entry->collection()?->title(),
            'site' => $entry->locale(),
        ];
    }
}
