<?php

declare(strict_types=1);

namespace Uptms\Publishing\Services;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Applies a drag-and-drop order. The client sends every id of the list in
 * its new order; ids outside `$scope` (another page, another group) or a
 * partial list are refused, so a stale tab can't scramble the order.
 */
final class Reorderer
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $scope
     * @param  list<int>  $ids
     */
    public function apply(Builder $scope, array $ids, string $column = 'sort_order'): void
    {
        $existing = (clone $scope)->pluck($scope->getModel()->getQualifiedKeyName())->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $given = collect($ids)->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

        if ($existing !== $given) {
            throw new GenericDomainException(ErrorCode::Conflict, 'The list changed since you opened it. Reload and try again.', [
                'ids' => ['Send every item of the list exactly once.'],
            ]);
        }

        DB::transaction(function () use ($scope, $ids, $column): void {
            foreach ($ids as $position => $id) {
                (clone $scope)->whereKey($id)->update([$column => $position + 1]);
            }
        });
    }
}
