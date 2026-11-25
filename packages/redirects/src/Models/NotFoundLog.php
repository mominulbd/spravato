<?php

declare(strict_types=1);

namespace Uptms\Redirects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property int $hits
 * @property int $google_hits
 * @property string $referrer_kind
 * @property string|null $referrer_path
 * @property string $status
 * @property bool $is_bot
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $resolved_at
 */
class NotFoundLog extends Model
{
    protected $fillable = ['path'];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'google_hits' => 'integer',
            'is_bot' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
