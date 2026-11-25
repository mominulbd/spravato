<?php

declare(strict_types=1);

namespace Uptms\Menus\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property-read Collection<int, MenuItem> $items
 */
class Menu extends Model
{
    protected $fillable = ['key', 'name', 'description', 'sort_order'];

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
