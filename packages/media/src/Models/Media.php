<?php

declare(strict_types=1);

namespace Uptms\Media\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Uptms\AuditLog\Concerns\Auditable;
use Uptms\Media\Factories\MediaFactory;

/**
 * A file in the library. Images are stored re-encoded (metadata stripped)
 * with WebP/AVIF variants; documents are stored as uploaded.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $folder_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $original_name
 * @property string $mime
 * @property string $kind
 * @property int $size
 * @property int $original_size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property bool $is_decorative
 * @property string|null $caption
 * @property float $focal_x
 * @property float $focal_y
 * @property int|null $uploaded_by
 * @property Carbon|null $first_used_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $usages_count
 * @property-read MediaFolder|null $folder
 * @property-read Collection<int, MediaVariant> $variants
 * @property-read Collection<int, MediaUsage> $usages
 */
class Media extends Model
{
    use Auditable;

    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    public const string IMAGE = 'image';

    public const string DOCUMENT = 'document';

    protected $table = 'media';

    protected $fillable = ['filename', 'alt', 'is_decorative', 'caption', 'focal_x', 'focal_y', 'folder_id'];

    /** @var list<string> */
    protected array $auditExclude = ['uuid', 'first_used_at', 'last_used_at'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'original_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'is_decorative' => 'boolean',
            'focal_x' => 'float',
            'focal_y' => 'float',
            'first_used_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }

    /** @return BelongsTo<MediaFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class);
    }

    /** @return HasMany<MediaVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class)->orderBy('format')->orderBy('width');
    }

    /** @return HasMany<MediaUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class)->orderBy('label');
    }

    public function isImage(): bool
    {
        return $this->kind === self::IMAGE;
    }

    /** Images need alt text before they can be placed, unless decorative. */
    public function needsAltText(): bool
    {
        return $this->isImage() && ! $this->is_decorative && ($this->alt === null || trim($this->alt) === '');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** Bytes the website actually sends: the largest WebP, else the original. */
    public function deliveredSize(): int
    {
        $webp = $this->relationLoaded('variants')
            ? $this->variants->where('format', 'webp')->sortByDesc('width')->first()
            : null;

        return $webp instanceof MediaVariant ? $webp->size : $this->size;
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public function scopeMissingAlt(Builder $query): Builder
    {
        return $query->where('kind', self::IMAGE)
            ->where('is_decorative', false)
            ->where(fn (Builder $q) => $q->whereNull('alt')->orWhere('alt', ''));
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereDoesntHave('usages');
    }
}
