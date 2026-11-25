<?php

declare(strict_types=1);

namespace Uptms\Seo\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Seo\Jobs\RunSiteAudit;
use Uptms\Seo\Models\SeoAudit;
use Uptms\Seo\Services\CoreWebVitals;
use Uptms\Seo\Services\SchemaGraph;
use Uptms\Seo\Services\SearchConsole;
use Uptms\Seo\Services\SiteAudit;

/** Figma 19 and the dashboard's SEO numbers: the latest finished site audit. */
class SeoAuditController
{
    public function show(SearchConsole $console, CoreWebVitals $vitals): JsonResponse
    {
        $latest = SeoAudit::query()->where('status', 'done')->latest('started_at')->latest('id')->first();
        $running = SeoAudit::query()->where('status', 'running')->latest('id')->first();
        $issues = $latest->issues ?? [];
        $lastWeek = $latest === null ? null : SeoAudit::query()->where('status', 'done')->where('started_at', '<=', $latest->started_at->copy()->subDays(7))->latest('started_at')->first();

        return ApiResponse::success([
            'audit' => $latest === null ? null : [
                'id' => $latest->id,
                'score' => $latest->score,
                'previous_score' => $lastWeek?->score,
                'urls' => $latest->urls,
                'indexable' => $latest->indexable,
                'indexed' => $latest->indexed,
                'trigger' => $latest->trigger,
                'started_at' => $latest->started_at->toIso8601String(),
                'finished_at' => $latest->finished_at?->toIso8601String(),
            ],
            'running' => $running !== null && $running->isRunning(),
            'issues' => $issues,
            'open' => array_sum(array_map(fn (array $i): int => SiteAudit::open($i['severity']) ? $i['count'] : 0, $issues)),
            'fixed_this_week' => $latest === null ? 0 : $this->fixedThisWeek($latest),
            'search' => $latest?->search,
            'vitals' => $latest?->vitals,
            'connections' => ['search_console' => $console->connected(), 'core_web_vitals' => $vitals->configured()],
            'site_host' => (string) parse_url(SchemaGraph::siteUrl(), PHP_URL_HOST),
        ]);
    }

    /** "Run audit now": queued; a run already in progress is reused. */
    public function run(Request $request, SiteAudit $audit, AuditRecorder $recorder): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        $run = $audit->start('manual', is_int($userId) ? $userId : null);

        if ($run->wasRecentlyCreated) {
            $recorder->record('seo.audit_run', null, null, null, label: 'Site audit');
            RunSiteAudit::dispatch($run->id);
        }

        return ApiResponse::success(['running' => $run->fresh()?->status === 'running'], 'The audit is running. Results appear here in a minute.', status: 202);
    }

    /** "Export report": every finding of the latest audit as CSV. */
    public function export(): StreamedResponse
    {
        $latest = SeoAudit::query()->where('status', 'done')->latest('started_at')->latest('id')->first();

        return new StreamedResponse(function () use ($latest): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['severity', 'issue', 'affected', 'address', 'checked_at'], escape: '');
            $checked = $latest?->started_at->toIso8601String() ?? '';
            foreach ($latest->issues ?? [] as $issue) {
                if ($issue['items'] === []) {
                    fputcsv($out, [$issue['severity'], $issue['title'], $issue['detail'], '', $checked], escape: '');
                }
                foreach ($issue['items'] as $item) {
                    fputcsv($out, [$issue['severity'], $issue['title'], $item['label'], $item['path'] ?? '', $checked], escape: '');
                }
            }
            fclose($out);
        }, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="seo-audit-'.now()->format('Y-m-d').'.csv"']);
    }

    /** Problems seen by any audit in the last 7 days that the latest no longer finds. */
    private function fixedThisWeek(SeoAudit $latest): int
    {
        $keys = fn (SeoAudit $audit): array => array_merge([], ...array_map(
            fn (array $i): array => SiteAudit::open($i['severity']) ? array_map(fn (array $item): string => $i['key'].'|'.$item['key'], $i['items']) : [],
            $audit->issues ?? [],
        ));
        $seen = [];

        SeoAudit::query()->where('status', 'done')->where('id', '!=', $latest->id)->where('started_at', '>=', now()->subDays(7))->each(function (SeoAudit $audit) use (&$seen, $keys): void {
            foreach ($keys($audit) as $key) {
                $seen[$key] = true;
            }
        });

        return count(array_diff_key($seen, array_flip($keys($latest))));
    }
}
