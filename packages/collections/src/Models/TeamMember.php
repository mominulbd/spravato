<?php

declare(strict_types=1);

namespace Uptms\Collections\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $name
 * @property string|null $credentials
 * @property string $role_title
 * @property string|null $bio
 * @property list<string>|null $highlights
 * @property int|null $photo_id
 * @property list<string>|null $show_on
 * @property bool $is_founder
 * @property string $schema_type
 * @property string|null $licence_number
 */
class TeamMember extends CollectionItem
{
    use SoftDeletes;

    protected $fillable = ['name', 'credentials', 'role_title', 'bio', 'highlights', 'photo_id', 'show_on', 'is_founder', 'schema_type', 'licence_number', 'sort_order'];

    protected function casts(): array
    {
        return ['highlights' => 'array', 'show_on' => 'array', 'is_founder' => 'boolean', 'is_published' => 'boolean', 'photo_id' => 'integer'];
    }
}
