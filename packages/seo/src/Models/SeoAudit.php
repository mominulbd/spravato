<?php

declare(strict_types=1);

namespace Uptms\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property string $trigger
 * @property int|null $triggered_by
 * @property int|null $score
 * @property int $urls
 * @property int $indexable
 * @property int|null $indexed
 * @property list<array{key: string, severity: string, title: string, detail: string, count: int, items: list<array{key: string, label: string, path: string|null, admin_path: string|null}>, action: array{label: string, to: string}|null}>|null $issues
 * @property array{clicks: int, impressions: int, position: float|null, queries: list<array{query: string, clicks: int, position: float}>}|null $search
 * @property array{passing: bool, metrics: list<array{key: string, label: string, value: float, unit: string, rating: string, good: float, poor: float}>}|null $vitals
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
class SeoAudit extends Model
{
    /** A run still "running" after this long died with its worker. */
    public const int TIMEOUT_MINUTES = 10;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'urls' => 'integer',
            'indexable' => 'integer',
            'indexed' => 'integer',
            'issues' => 'array',
            'search' => 'array',
            'vitals' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === 'running' && $this->started_at->gt(now()->subMinutes(self::TIMEOUT_MINUTES));
    }
}
