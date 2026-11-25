<?php

declare(strict_types=1);

namespace Uptms\Collections\Models;

/**
 * @property string $name
 * @property int|null $logo_id
 * @property string|null $url
 * @property string|null $note
 */
class InsurancePartner extends CollectionItem
{
    protected $fillable = ['name', 'logo_id', 'url', 'note', 'sort_order'];

    protected function casts(): array
    {
        return ['logo_id' => 'integer', 'is_published' => 'boolean'];
    }
}
