<?php

declare(strict_types=1);

namespace Uptms\AuditLog\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uptms\AuditLog\Http\Resources\AuditLogResource;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\AuditLog\Services\AuditRecorder;

/**
 * Figma screen 27: search, actor, action category and date range, newest
 * first, plus a CSV export of the same filter. Exports are audited too.
 */
class AuditLogController
{
    /** Action categories the filter offers → action prefixes. */
    public const array CATEGORIES = [
        'sign_in' => ['auth.'],
        'users' => ['user.', 'permission.', 'role.'],
        'consultations' => ['consultation'],
        'content' => ['page.', 'post.', 'condition.', 'collection', 'menu', 'media.', 'quiz'],
        'seo' => ['seo', 'redirect.', 'sitemap'],
        'settings' => ['setting'],
        'audit' => ['audit.'],
    ];

    private const int EXPORT_LIMIT = 50_000;

    public function index(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $logs = $this->query($validated)
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        return ApiResponse::paginated($logs, AuditLogResource::class, 'Audit log retrieved.', [
            'categories' => array_keys(self::CATEGORIES),
        ]);
    }

    public function export(Request $request, AuditRecorder $audit): StreamedResponse
    {
        $validated = $this->validated($request);
        $audit->record('audit.exported', null, null, ['filter' => $validated['filter'] ?? [], 'search' => $validated['search'] ?? null]);
        $query = $this->query($validated)->with('user')->orderByDesc('id')->limit(self::EXPORT_LIMIT);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['id', 'when_utc', 'user', 'action', 'record_type', 'record_id', 'record', 'ip_address', 'request_id'], escape: '');

            foreach ($query->lazyById(500, 'audit_logs.id') as $log) {
                /** @var AuditLog $log */
                fputcsv($out, [
                    $log->id,
                    $log->created_at->toIso8601String(),
                    $log->user?->getAttribute('name') ?? 'System',
                    $log->action,
                    $log->auditable_type,
                    $log->auditable_id,
                    $this->csvSafe($log->auditable_label),
                    $log->ip_address,
                    $log->request_id,
                ], escape: '');
            }

            fclose($out);
        }, 'audit-log-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.user_id' => ['sometimes', 'integer'],
            'filter.category' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::CATEGORIES))],
            'filter.action' => ['sometimes', 'string', 'max:60'],
            'filter.auditable_type' => ['sometimes', 'string', 'max:40'],
            'filter.auditable_id' => ['sometimes', 'string', 'max:36'],
            'filter.from' => ['sometimes', 'date'],
            'filter.to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return Builder<AuditLog>
     */
    private function query(array $validated): Builder
    {
        /** @var array<string, string> $filter */
        $filter = $validated['filter'] ?? [];
        $search = $validated['search'] ?? null;

        return AuditLog::query()
            ->when(is_string($search) && $search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('action', 'like', '%'.$search.'%')
                ->orWhere('auditable_label', 'like', '%'.$search.'%')))
            ->when(isset($filter['user_id']), fn (Builder $q) => $q->where('user_id', $filter['user_id']))
            ->when(isset($filter['category']), fn (Builder $q) => $q->where(function (Builder $w) use ($filter): void {
                foreach (self::CATEGORIES[$filter['category']] as $prefix) {
                    $w->orWhere('action', 'like', $prefix.'%');
                }
            }))
            ->when(isset($filter['action']), fn (Builder $q) => $q->where('action', $filter['action']))
            ->when(isset($filter['auditable_type']), fn (Builder $q) => $q->where('auditable_type', $filter['auditable_type']))
            ->when(isset($filter['auditable_id']), fn (Builder $q) => $q->where('auditable_id', $filter['auditable_id']))
            ->when(isset($filter['from']), fn (Builder $q) => $q->where('created_at', '>=', $filter['from']))
            ->when(isset($filter['to']), fn (Builder $q) => $q->where('created_at', '<=', $filter['to']));
    }

    /** Spreadsheet apps execute cells that start with = + - @. */
    private function csvSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
