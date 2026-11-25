<?php

declare(strict_types=1);

namespace Uptms\Collections\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\DB;
use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Types\CollectionType;
use Uptms\Media\Services\MediaUsageRecorder;
use Uptms\Publishing\Events\ContentPublished;

final readonly class SaveCollectionItem
{
    public function __construct(private MediaUsageRecorder $usages) {}

    /**
     * @param  array<string, mixed>  $fields  validated against the type's rules
     *
     * @throws AuthorizationException
     */
    public function execute(CollectionType $type, ?CollectionItem $item, array $fields, ?bool $publish, Authorizable $actor): CollectionItem
    {
        $model = $type->model();
        $item ??= new $model;
        $wasPublished = $item->exists && $item->is_published;
        $willPublish = $publish ?? $wasPublished;

        if (($willPublish || $wasPublished) && ! $actor->can('publish', CollectionItem::class)) {
            throw new AuthorizationException('Changing what the website shows requires the content.publish permission.');
        }

        return DB::transaction(function () use ($type, $item, $fields, $willPublish, $wasPublished): CollectionItem {
            if ($type->fixedGroup() !== null) {
                $fields['group'] = $type->fixedGroup();
            }

            $item->fill($fields);
            $item->is_published = $willPublish;

            if (! $item->exists) {
                $group = $type->groupColumn();
                $item->sort_order = (int) $type->query()
                    ->when($group !== null, fn ($q) => $q->where((string) $group, $fields[(string) $group] ?? null))
                    ->max('sort_order') + 1;
            }

            $item->save();

            $this->syncMedia($type, $item);

            if ($willPublish || $wasPublished) {
                ContentPublished::dispatch(['content']);
            }

            return $item;
        });
    }

    public function syncMedia(CollectionType $type, CollectionItem $item): void
    {
        $usages = [];

        foreach ($type->mediaFields() as $column => $context) {
            $id = $item->getAttribute($column);

            if (is_int($id)) {
                $usages[] = ['media_id' => $id, 'context' => $context];
            }
        }

        if ($type->mediaFields() !== []) {
            $this->usages->sync($item, $usages, $type->itemLabel($item), '/collections/'.$type->key());
        }
    }
}
