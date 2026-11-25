<?php

declare(strict_types=1);

namespace Uptms\Pages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section in a page's working copy.
 *
 * @property int $id
 * @property int $page_id
 * @property string $type
 * @property array<string, mixed> $data
 * @property bool $is_visible
 * @property int $sort_order
 */
class PageBlock extends Model
{
    protected $fillable = ['type', 'data', 'is_visible', 'sort_order'];

    protected function casts(): array
    {
        return ['data' => 'array', 'is_visible' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
