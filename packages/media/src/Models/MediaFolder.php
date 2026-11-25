<?php

declare(strict_types=1);

namespace Uptms\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Uptms\AuditLog\Concerns\Auditable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $media_count
 */
class MediaFolder extends Model
{
    use Auditable;

    protected $fillable = ['name', 'slug', 'sort_order'];

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }
}
