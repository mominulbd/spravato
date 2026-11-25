<?php

declare(strict_types=1);

namespace Uptms\Redirects\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Redirects\Models\NotFoundLog;
use Uptms\Redirects\Services\NotFoundMonitor;
use Uptms\Redirects\Services\RedirectRules;

/** Figma 21: missing addresses with traffic, and one-click fixes. */
class NotFoundController
{
    public function __construct(private readonly NotFoundMonitor $monitor, private readonly RedirectRules $rules) {}

    /** `?filter[status]=open|ignored|resolved&per_page` — busiest first. */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['filter.status' => ['sometimes', Rule::in(['open', 'ignored', 'resolved'])], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $status = $validated['filter']['status'] ?? 'open';
        $page = NotFoundLog::query()->where('status', $status)->orderByDesc('hits')->orderByDesc('last_seen_at')
            ->paginate((int) ($validated['per_page'] ?? 25))->withQueryString();
        $known = $this->rules->knownPaths();
        /** @var list<NotFoundLog> $items */
        $items = $page->items();

        return new JsonResponse([
            'success' => true,
            'message' => 'Missing addresses retrieved.',
            'data' => array_map(fn (NotFoundLog $log): array => [
                'id' => $log->id,
                'path' => $log->path,
                'hits' => $log->hits,
                'google_hits' => $log->google_hits,
                'last_seen_at' => $log->last_seen_at->toIso8601String(),
                'referrer' => ['kind' => $log->referrer_kind, 'path' => $log->referrer_path],
                'is_bot' => $log->is_bot,
                'status' => $log->status,
                'suggestion' => $log->is_bot ? null : $this->monitor->suggest($log->path, $known),
            ], $items),
            'meta' => [
                'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
                'last_page' => $page->lastPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem(),
                'counts' => NotFoundLog::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn (mixed $n): int => (int) $n)->all() + ['open' => 0, 'ignored' => 0, 'resolved' => 0],
                'stats' => $this->monitor->stats(),
            ],
            'links' => ['first' => $page->url(1), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl(), 'last' => $page->url($page->lastPage())],
        ]);
    }

    /** "Create redirect" (to the suggestion or a chosen page), or "Mark 410" when `status_code` is 410. */
    public function resolve(Request $request, NotFoundLog $log, AuditRecorder $audit): JsonResponse
    {
        $validated = $request->validate(['to_path' => ['nullable', 'string', 'max:300', 'regex:/^(\/|https:\/\/)/'], 'status_code' => ['sometimes', Rule::in([301, 410])]]);
        $actor = $request->user()?->getAuthIdentifier();

        DB::transaction(function () use ($validated, $log, $actor, $audit): void {
            $this->rules->save(null, ['from_path' => $log->path, 'to_path' => $validated['to_path'] ?? null, 'status_code' => (int) ($validated['status_code'] ?? 301), 'note' => 'From the 404 monitor'], is_int($actor) ? $actor : null, 'not_found');
            $before = $log->status;
            $log->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();
            $audit->record('not_found.resolved', null, ['status' => $before], ['path' => $log->path, 'status' => 'resolved', 'to_path' => $validated['to_path'] ?? null, 'status_code' => (int) ($validated['status_code'] ?? 301)], label: $log->path);
        });

        return ApiResponse::success(null, ($validated['status_code'] ?? 301) === 410 ? 'Marked as gone (410).' : 'Redirect created.');
    }

    public function ignore(NotFoundLog $log, AuditRecorder $audit): JsonResponse
    {
        $log->forceFill(['status' => 'ignored'])->save();
        $audit->record('not_found.ignored', null, null, ['path' => $log->path], label: $log->path);

        return ApiResponse::success(null, 'Ignored.');
    }

    public function restore(NotFoundLog $log, AuditRecorder $audit): JsonResponse
    {
        $before = $log->status;
        $log->forceFill(['status' => 'open', 'resolved_at' => null])->save();
        $audit->record('not_found.restored', null, ['status' => $before], ['path' => $log->path, 'status' => 'open'], label: $log->path);

        return ApiResponse::success(null, 'Back in “Needs attention”.');
    }

    public function clearIgnored(AuditRecorder $audit): JsonResponse
    {
        $count = NotFoundLog::query()->where('status', 'ignored')->delete();
        $audit->record('not_found.cleared', null, null, ['deleted' => $count], label: '404 monitor');

        return ApiResponse::success(['deleted' => $count], 'Ignored addresses cleared.');
    }

    public function export(): StreamedResponse
    {
        return new StreamedResponse(function (): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['path', 'hits', 'from_google', 'last_seen', 'referrer', 'status'], escape: '');
            NotFoundLog::query()->orderByDesc('hits')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $log) {
                    /** @var NotFoundLog $log */
                    fputcsv($out, [$log->path, $log->hits, $log->google_hits, $log->last_seen_at->toDateString(), $log->referrer_kind.($log->referrer_path ? ' '.$log->referrer_path : ''), $log->status], escape: '');
                }
            });
            fclose($out);
        }, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="404s-'.now()->format('Y-m-d').'.csv"']);
    }
}
