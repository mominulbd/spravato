<?php

declare(strict_types=1);

namespace Uptms\AuditLog\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\AuditLog\Services\AuditRecorder;

/**
 * Records create/update/delete of a model with a before/after diff.
 *
 * Only changed attributes are stored; hidden attributes never reach the log.
 * Models may exclude noisy columns with `$auditExclude`. Business actions
 * (sign-in, invitation sent …) are recorded explicitly by their Action.
 *
 * @mixin Model
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            /** @var Model&self $model */
            $model->writeAudit('created', null, $model->auditSnapshot($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            /** @var Model&self $model */
            $changes = $model->auditSnapshot($model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->auditSnapshot($model->getOriginal()), $changes);
            $model->writeAudit('updated', $old, $changes);
        });

        static::deleted(function (Model $model): void {
            /** @var Model&self $model */
            $model->writeAudit('deleted', $model->auditSnapshot($model->getAttributes()), null);
        });
    }

    /** @return MorphMany<AuditLog, $this> */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    /** `user.updated`, `role.created` … */
    protected function auditActionPrefix(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function writeAudit(string $event, ?array $old, ?array $new): void
    {
        app(AuditRecorder::class)->record($this->auditActionPrefix().'.'.$event, $this, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditSnapshot(array $attributes): array
    {
        $exclude = array_merge(
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
            ['created_at', 'remember_token'],
        );

        return array_diff_key($attributes, array_flip($exclude));
    }
}
