<?php

declare(strict_types=1);

namespace Uptms\Collections\Services;

use Illuminate\Support\Collection;
use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Types\CollectionType;
use Uptms\Media\Services\MediaPresenter;

/**
 * Presents collection rows with their images resolved in one query. Used
 * by the admin resource, the public endpoint and the page-block sources.
 */
final readonly class CollectionReader
{
    public function __construct(private MediaPresenter $media) {}

    /**
     * Published items the website shows.
     *
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function published(CollectionType $type, array $params = []): array
    {
        $items = $type->applyPublicFilters($type->query()->published(), $params)->ordered()->get();

        return array_map(fn (array $item): array => array_merge(['id' => $item['id']], $item['fields']), $this->presentAll($type, $items));
    }

    /**
     * @param  Collection<int, CollectionItem>  $items
     * @return list<array{id: int, fields: array<string, mixed>, item: CollectionItem}>
     */
    public function presentAll(CollectionType $type, Collection $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            foreach (array_keys($type->mediaFields()) as $column) {
                $id = $item->getAttribute($column);

                if (is_int($id)) {
                    $ids[] = $id;
                }
            }
        }

        $images = $this->media->presentMany($ids);

        return array_values($items->map(fn (CollectionItem $item): array => [
            'id' => $item->id,
            'fields' => $type->present($item, $images),
            'item' => $item,
        ])->all());
    }
}
