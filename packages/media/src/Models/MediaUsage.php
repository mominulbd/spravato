<?php

declare(strict_types=1);

namespace Uptms\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a file is placed. Written only through MediaUsageRecorder.
 *
 * @property int $id
 * @property int $media_id
 * @property string $usable_type
 * @property int $usable_id
 * @property string $context
 * @property string $label
 * @property string|null $admin_path
 */
class MediaUsage extends Model
{
    protected $fillable = ['media_id', 'usable_type', 'usable_id', 'context', 'label', 'admin_path'];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
