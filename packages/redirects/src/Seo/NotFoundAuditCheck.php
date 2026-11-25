<?php

declare(strict_types=1);

namespace Uptms\Redirects\Seo;

use Illuminate\Support\Facades\DB;
use Uptms\Seo\Contracts\SeoAuditCheck;
use Uptms\Seo\Domain\Enums\IssueSeverity;
use Uptms\Seo\Domain\SeoIssue;

/**
 * Figma 19's "A removed page still gets visits": missing addresses people
 * (not scanners) kept reaching this week. A redirect keeps them and their
 * search ranking.
 */
final readonly class NotFoundAuditCheck implements SeoAuditCheck
{
    /** Visits in 7 days before an address counts. */
    public const int MIN_VISITS = 3;

    public function issues(array $subjects): iterable
    {
        $rows = DB::table('not_found_hits')
            ->join('not_found_logs', 'not_found_logs.id', '=', 'not_found_hits.not_found_log_id')
            ->where('not_found_logs.status', 'open')
            ->where('not_found_logs.is_bot', false)
            ->where('not_found_hits.day', '>', now()->subDays(7)->toDateString())
            ->groupBy('not_found_logs.path')
            ->havingRaw('SUM(not_found_hits.hits) >= ?', [self::MIN_VISITS])
            ->orderByRaw('SUM(not_found_hits.hits) DESC')
            ->limit(50)
            ->get(['not_found_logs.path', DB::raw('SUM(not_found_hits.hits) as visits')]);

        if ($rows->isEmpty()) {
            yield SeoIssue::passed('not_found_visited', 'No missing pages are getting visits', 'From the 404 monitor, last 7 days');

            return;
        }

        $visits = (int) $rows->sum('visits');
        $items = array_values($rows->map(fn (object $row): array => ['key' => (string) $row->path, 'label' => (string) $row->path, 'path' => (string) $row->path, 'admin_path' => '/seo/404'])->all());
        $first = $items[0]['path'];

        yield new SeoIssue(
            'not_found_visited',
            IssueSeverity::Urgent,
            count($items) === 1 ? 'A removed page still gets visits' : 'Removed pages still get visits',
            (count($items) === 1 ? "{$first} → 404" : SeoIssue::names($items, 2)).' · '.$visits.' '.($visits === 1 ? 'visit' : 'visits').' this week',
            $items,
            ['label' => 'Redirect', 'to' => '/seo/404'],
        );
    }
}
