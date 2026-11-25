<?php

declare(strict_types=1);

namespace Uptms\Collections\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Uptms\Collections\Actions\SaveCollectionItem;
use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Services\CollectionReader;
use Uptms\Collections\Services\CollectionTypes;
use Uptms\Collections\Types\CollectionType;
use Uptms\Media\Services\MediaUsageRecorder;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Publishing\Services\Reorderer;

/**
 * Figma 14–15: one endpoint set for every collection tab
 * (`/admin/collections/{type}`). Lists are short and ordered by hand, so
 * they are returned whole rather than paginated.
 */
class CollectionController
{
    public function __construct(
        private readonly CollectionTypes $types,
        private readonly CollectionReader $reader,
    ) {}

    /** Tab names and counts. */
    public function types(): JsonResponse
    {
        Gate::authorize('viewAny', CollectionItem::class);

        $tabs = array_values(array_map(fn (CollectionType $type): array => [
            'key' => $type->key(),
            'label' => $type->label(),
            'count' => $type->query()->count(),
            'grouped' => $type->groupColumn() !== null,
            'groups' => $type->groupColumn() !== null ? $this->groupCounts($type) : [],
        ], $this->types->all()));

        return ApiResponse::success($tabs, 'Collections retrieved.');
    }

    public function index(Request $request, string $type): JsonResponse
    {
        Gate::authorize('viewAny', CollectionItem::class);
        $collection = $this->types->get($type);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.group' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);
        $search = $validated['search'] ?? null;
        $group = $validated['filter']['group'] ?? null;

        $items = $collection->query()
            ->when($collection->groupColumn() !== null && $group !== null && $group !== '', fn (Builder $q) => $q->where((string) $collection->groupColumn(), $group))
            ->when($search !== null && $search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($collection, $search): void {
                foreach ($collection->searchColumns() as $column) {
                    $w->orWhere($column, 'like', '%'.$search.'%');
                }
            }))
            ->ordered()
            ->get();

        return ApiResponse::success($this->present($collection, array_values($items->all())), 'Items retrieved.', [
            'groups' => $collection->groupColumn() !== null ? $this->groupCounts($collection) : [],
        ]);
    }

    public function store(Request $request, string $type, SaveCollectionItem $action): JsonResponse
    {
        Gate::authorize('create', CollectionItem::class);
        $collection = $this->types->get($type);
        [$fields, $publish] = $this->validated($request, $collection);

        /** @var Authorizable $actor */
        $actor = $request->user();
        $item = $action->execute($collection, null, $fields, $publish ?? false, $actor);

        return ApiResponse::created($this->present($collection, [$item])[0], 'Item added.');
    }

    public function update(Request $request, string $type, int $id, SaveCollectionItem $action): JsonResponse
    {
        $collection = $this->types->get($type);
        $item = $this->find($collection, $id);
        Gate::authorize('update', $item);
        [$fields, $publish] = $this->validated($request, $collection);

        /** @var Authorizable $actor */
        $actor = $request->user();
        $item = $action->execute($collection, $item, $fields, $publish, $actor);

        return ApiResponse::success($this->present($collection, [$item])[0], 'Changes saved.');
    }

    public function destroy(string $type, int $id, MediaUsageRecorder $usages): JsonResponse
    {
        $collection = $this->types->get($type);
        $item = $this->find($collection, $id);
        Gate::authorize('delete', $item);

        DB::transaction(function () use ($collection, $item, $usages): void {
            if ($collection->mediaFields() !== []) {
                $usages->clear($item);
            }

            $item->delete();
        });

        if ($item->is_published) {
            ContentPublished::dispatch(['content']);
        }

        return ApiResponse::success(null, 'Item deleted.');
    }

    public function reorder(Request $request, string $type, Reorderer $reorderer): JsonResponse
    {
        $collection = $this->types->get($type);
        Gate::authorize('publish', CollectionItem::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
            'group' => [$collection->groupColumn() !== null ? 'required' : 'prohibited', 'string', 'max:80'],
        ]);

        $scope = $collection->query()->when(
            $collection->groupColumn() !== null,
            fn (Builder $q) => $q->where((string) $collection->groupColumn(), $validated['group']),
        );
        /** @var list<int> $ids */
        $ids = array_map('intval', $validated['ids']);
        $reorderer->apply($scope, $ids);
        ContentPublished::dispatch(['content']);

        return ApiResponse::success(null, 'Order saved.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool|null}
     */
    private function validated(Request $request, CollectionType $collection): array
    {
        $validated = $request->validate([...$collection->rules(), 'is_published' => ['sometimes', 'boolean']]);
        $publish = array_key_exists('is_published', $validated) ? (bool) $validated['is_published'] : null;
        unset($validated['is_published']);

        // Keep only top-level fields the type knows (rules may contain `x.*`).
        $fields = array_intersect_key($validated, array_flip(array_filter(array_keys($collection->rules()), fn (string $key): bool => ! str_contains($key, '.'))));

        return [$fields, $publish];
    }

    private function find(CollectionType $collection, int $id): CollectionItem
    {
        /** @var CollectionItem */
        return $collection->query()->findOrFail($id);
    }

    /**
     * @param  list<CollectionItem>  $items
     * @return list<array<string, mixed>>
     */
    private function present(CollectionType $collection, array $items): array
    {
        return array_map(fn (array $row): array => [
            'id' => $row['id'],
            ...$row['fields'],
            'is_published' => $row['item']->is_published,
            'sort_order' => $row['item']->sort_order,
            'updated_at' => $row['item']->updated_at?->toIso8601String(),
        ], $this->reader->presentAll($collection, collect($items)));
    }

    /** @return list<array{value: string, count: int}> */
    private function groupCounts(CollectionType $collection): array
    {
        $column = (string) $collection->groupColumn();

        $order = ['home' => 0, 'tms' => 1];

        $counts = $collection->query()
            ->toBase()
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->get()
            ->map(fn (object $row): array => ['value' => (string) $row->{$column}, 'count' => (int) $row->aggregate])
            ->sortBy(fn (array $row): string => ($order[$row['value']] ?? 2).$row['value'])
            ->values()
            ->all();

        return array_values($counts);
    }
}
