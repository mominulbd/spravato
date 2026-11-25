<?php

declare(strict_types=1);

namespace Uptms\Collections\Types;

use Illuminate\Database\Eloquent\Builder;
use Uptms\Collections\Models\CollectionItem;

/**
 * Describes one collection tab of Figma 14–15: its model, fields, how it is
 * grouped and what the website receives. The admin controller, the public
 * endpoint and the page-block source are generic over these descriptions.
 */
abstract class CollectionType
{
    /** URL key: `team`, `faqs`, `trust-points` … */
    abstract public function key(): string;

    abstract public function label(): string;

    /** @return class-string<CollectionItem> */
    abstract public function model(): string;

    /**
     * Validation for the editable fields (not visibility or order).
     *
     * @return array<string, list<mixed>>
     */
    abstract public function rules(): array;

    /**
     * Fields shared by the admin and the website.
     *
     * @param  array<int, array<string, mixed>>  $images  presented media by id
     * @return array<string, mixed>
     */
    abstract public function present(CollectionItem $item, array $images): array;

    /** Shown in the audit log and the media library ("Team · Ayub Mwaura"). */
    abstract public function itemLabel(CollectionItem $item): string;

    /** @return list<string> columns searched by `?search=` */
    abstract public function searchColumns(): array;

    /** Column that splits the list into groups (FAQs by page), if any. */
    public function groupColumn(): ?string
    {
        return null;
    }

    /** For types stored in a shared table under a fixed group. */
    public function fixedGroup(): ?string
    {
        return null;
    }

    /**
     * Media columns and how the library names the place they are used.
     *
     * @return array<string, string> column → context
     */
    public function mediaFields(): array
    {
        return [];
    }

    /**
     * Narrow the website's list with block parameters (e.g. a FAQ group).
     *
     * @param  Builder<CollectionItem>  $query
     * @param  array<string, mixed>  $params
     * @return Builder<CollectionItem>
     */
    public function applyPublicFilters(Builder $query, array $params): Builder
    {
        return $query;
    }

    /**
     * Choices offered when a page block uses this collection.
     *
     * @return list<array{value: string, label: string}>
     */
    public function sourceOptions(): array
    {
        return [];
    }

    /** @return Builder<CollectionItem> */
    public function query(): Builder
    {
        $model = $this->model();
        /** @var Builder<CollectionItem> $query */
        $query = $model::query();

        if ($this->fixedGroup() !== null) {
            $query->where('group', $this->fixedGroup());
        }

        return $query;
    }
}
