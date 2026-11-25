<?php

declare(strict_types=1);

namespace Uptms\AuditLog\Services;

use Illuminate\Database\Eloquent\Model;
use Uptms\AuditLog\Models\AuditLog;

/**
 * Read side of the audit log for other modules: a record's own history
 * (e.g. the Activity card of Figma 05) without touching audit_logs directly.
 */
class AuditTrail
{
    /**
     * Newest first.
     *
     * @param  list<string>|null  $actions  only these actions
     * @return list<array{id: int, action: string, user: array{id: int, name: string}|null, new_values: array<string, mixed>|null, created_at: string}>
     */
    public function for(Model $model, int $limit = 20, ?array $actions = null): array
    {
        return array_values(AuditLog::query()
            ->with('user')
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->when($actions !== null, fn ($query) => $query->whereIn('action', $actions ?? []))
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (AuditLog $log): array {
                $user = $log->user;

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $user === null ? null : ['id' => (int) $user->getKey(), 'name' => (string) $user->getAttribute('name')],
                    'new_values' => $log->new_values,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            })
            ->all());
    }

    public function count(Model $model, string $action): int
    {
        return AuditLog::query()
            ->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey())
            ->where('action', $action)
            ->count();
    }
}
