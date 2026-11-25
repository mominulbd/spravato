<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Facades\Log;
use Throwable;
use Uptms\Seo\Audit\MetadataChecks;
use Uptms\Seo\Domain\Enums\IssueSeverity;
use Uptms\Seo\Domain\SeoIssue;
use Uptms\Seo\Models\SeoAudit;

/**
 * The site audit behind Figma 19 and the dashboard's SEO health. It reads
 * every published URL through SeoSources, runs the editors' checklist on
 * each, adds the site-wide checks and those other modules register, and
 * asks Google (when connected) what it sees.
 *
 * Score: the average checklist score of the indexable URLs, minus points
 * for each open issue kind (urgent 5, warning 2, notice 1), 0–100.
 */
final readonly class SiteAudit
{
    public function __construct(
        private SeoSources $sources,
        private SeoAuditChecks $checks,
        private MetadataChecks $metadata,
        private SeoPresenter $presenter,
        private SeoWorkspace $workspace,
        private SearchConsole $console,
        private CoreWebVitals $vitals,
    ) {}

    /** The run in progress, or a new one. */
    public function start(string $trigger, ?int $userId = null): SeoAudit
    {
        $running = SeoAudit::query()->where('status', 'running')->latest('id')->first();

        if ($running !== null && $running->isRunning()) {
            return $running;
        }

        SeoAudit::query()->where('status', 'running')->update(['status' => 'failed', 'finished_at' => now()]);

        return SeoAudit::query()->create(['status' => 'running', 'trigger' => $trigger, 'triggered_by' => $userId, 'started_at' => now()]);
    }

    public function run(SeoAudit $audit): SeoAudit
    {
        try {
            $audit->forceFill($this->results())->forceFill(['status' => 'done', 'finished_at' => now()])->save();
        } catch (Throwable $e) {
            $audit->forceFill(['status' => 'failed', 'finished_at' => now()])->save();
            Log::error('The site audit failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);

            throw $e;
        }

        SeoAudit::query()->where('started_at', '<', now()->subDays(90))->delete();

        return $audit;
    }

    /** @return array<string, mixed> */
    private function results(): array
    {
        $subjects = $this->sources->allPublished();
        $pages = [];

        foreach ($subjects as $subject) {
            $meta = $this->presenter->meta($subject);

            if (! $meta->robots_index) {
                continue;
            }

            $pages[] = [
                'subject' => $subject,
                'meta' => $meta,
                'title' => $this->presenter->title($subject, $meta),
                'description' => $this->presenter->description($subject, $meta),
                'analysis' => $this->workspace->analysis($subject, $meta),
            ];
        }

        $issues = $this->metadata->issues($pages);

        foreach ($this->checks->all() as $check) {
            foreach ($check->issues($subjects) as $issue) {
                $issues[] = $issue;
            }
        }

        usort($issues, fn (SeoIssue $a, SeoIssue $b): int => [$a->severity->rank(), -count($a->items)] <=> [$b->severity->rank(), -count($b->items)]);

        $average = $pages === [] ? 0 : array_sum(array_map(fn (array $p): int => (int) $p['analysis']['score'], $pages)) / count($pages);
        $penalty = array_sum(array_map(fn (SeoIssue $i): int => $i->severity->penalty(), $issues));

        return [
            'score' => $pages === [] ? null : max(0, min(100, (int) round($average) - $penalty)),
            'urls' => count($subjects),
            'indexable' => count($pages),
            'indexed' => $this->indexed(array_map(fn (array $p): string => $p['subject']->path, $pages)),
            'issues' => array_map(fn (SeoIssue $i): array => $i->toArray(), $issues),
            'search' => $this->search(),
            'vitals' => $this->vitals->fetch(SchemaGraph::siteUrl()),
        ];
    }

    /** @param list<string> $paths */
    private function indexed(array $paths): ?int
    {
        if (! $this->console->connected()) {
            return null;
        }

        $answers = array_map(fn (string $path): ?bool => $this->console->indexed(SchemaGraph::absolute($path)), $paths);

        return in_array(null, $answers, true) && ! in_array(true, $answers, true) ? null : count(array_filter($answers));
    }

    /** @return array{clicks: int, impressions: int, position: float|null, queries: list<array{query: string, clicks: int, position: float}>}|null */
    private function search(): ?array
    {
        $totals = $this->console->analytics(28);

        if ($totals === null) {
            return null;
        }

        $queries = $this->console->analytics(28, ['query'], 5) ?? [];

        return [
            'clicks' => (int) ($totals[0]['clicks'] ?? 0),
            'impressions' => (int) ($totals[0]['impressions'] ?? 0),
            'position' => isset($totals[0]['position']) ? round((float) $totals[0]['position'], 1) : null,
            'queries' => array_map(fn (array $row): array => [
                'query' => (string) ($row['keys'][0] ?? ''),
                'clicks' => (int) $row['clicks'],
                'position' => round((float) $row['position'], 1),
            ], $queries),
        ];
    }

    /** Issue severities that count as open. */
    public static function open(string $severity): bool
    {
        return $severity !== IssueSeverity::Passed->value;
    }
}
