<?php

declare(strict_types=1);

namespace Uptms\Collections\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Uptms\AuditLog\Concerns\Auditable;

/**
 * Base for every collection row: ordered, with a visibility flag that only
 * content.publish may turn on (Uptms\Collections\Actions\SaveCollectionItem).
 *
 * @property int $id
 * @property bool $is_published
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class CollectionItem extends Model
{
    use Auditable;

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
