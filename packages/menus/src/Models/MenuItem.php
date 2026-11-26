<?php

declare(strict_types=1);

namespace Uptms\Menus\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $label
 * @property string $type
 * @property int|null $page_id
 * @property int|null $media_id
 * @property string|null $anchor
 * @property string|null $url
 * @property bool $is_visible
 * @property int $sort_order
 */
class MenuItem extends Model
{
    protected $fillable = ['parent_id', 'label', 'type', 'page_id', 'media_id', 'anchor', 'url', 'is_visible', 'sort_order'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'page_id' => 'integer', 'media_id' => 'integer', 'parent_id' => 'integer', 'sort_order' => 'integer'];
    }
}
