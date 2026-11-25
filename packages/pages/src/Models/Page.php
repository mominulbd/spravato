<?php

declare(strict_types=1);

namespace Uptms\Pages\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Uptms\AuditLog\Concerns\Auditable;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Publishing\Concerns\HasRevisions;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Models\Revision;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $parent_id
 * @property string $title
 * @property string $slug
 * @property string $path
 * @property PageTemplate $template
 * @property PublishStatus $status
 * @property bool $is_home
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property int|null $published_revision_id
 * @property int|null $scheduled_revision_id
 * @property int $lock_version
 * @property string|null $draft_hash
 * @property string|null $published_hash
 * @property Carbon|null $content_updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, PageBlock> $blocks
 * @property-read Page|null $parent
 * @property-read Revision|null $publishedRevision
 */
class Page extends Model
{
    use Auditable;
    use HasRevisions;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = ['title'];

    /** @var list<string> */
    protected array $auditExclude = ['uuid', 'lock_version', 'draft_hash', 'published_hash', 'content_updated_at', 'updated_by'];

    protected function casts(): array
    {
        return [
            'template' => PageTemplate::class,
            'status' => PublishStatus::class,
            'is_home' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return HasMany<PageBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->status !== PublishStatus::Published || $this->draft_hash !== $this->published_hash;
    }

    /** "home", "tms", "conditions/depression" — the website's cache tag suffix. */
    public function cacheKey(): string
    {
        return $this->is_home ? 'home' : ltrim($this->path, '/');
    }
}
