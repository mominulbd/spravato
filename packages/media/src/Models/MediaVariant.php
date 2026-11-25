<?php

declare(strict_types=1);

namespace Uptms\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $media_id
 * @property string $format
 * @property int $width
 * @property int $height
 * @property string $path
 * @property int $size
 */
class MediaVariant extends Model
{
    protected $fillable = ['format', 'width', 'height', 'path', 'size'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'size' => 'integer'];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
