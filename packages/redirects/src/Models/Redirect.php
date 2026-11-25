<?php

declare(strict_types=1);

namespace Uptms\Redirects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Uptms\AuditLog\Concerns\Auditable;

/**
 * @property int $id
 * @property string $from_path
 * @property string|null $to_path
 * @property int $status_code
 * @property string $match_type
 * @property bool $is_enabled
 * @property string $source
 * @property string|null $note
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Redirect extends Model
{
    use Auditable;

    public const array CODES = [301, 302, 307, 410];

    protected $fillable = ['from_path', 'to_path', 'status_code', 'match_type', 'is_enabled', 'note'];

    /** @var list<string> */
    protected array $auditExclude = ['hits', 'last_hit_at'];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'is_enabled' => 'boolean',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }
}
