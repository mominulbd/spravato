<?php

declare(strict_types=1);

namespace Uptms\Publishing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An immutable content snapshot (see the migration).
 *
 * @property int $id
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property string $kind
 * @property array<string, mixed> $payload
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon $created_at
 */
class Revision extends Model
{
    public const string DRAFT = 'draft';

    public const string PUBLISHED = 'published';

    public const string SCHEDULED = 'scheduled';

    public const string RESTORED = 'restored';

    public const null UPDATED_AT = null;

    protected $fillable = ['kind', 'payload', 'note', 'user_id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Revisions are immutable.'));
    }

    /** @return MorphTo<Model, $this> */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }
}
