<?php

declare(strict_types=1);

namespace Uptms\Redirects\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Uptms\Redirects\Models\NotFoundLog;

/**
 * The 404 monitor (Figma 21): one row per missing address with hit counts
 * by day. Stored: the path, a referrer kind and — for links on the site
 * itself — the linking page. Never an IP address or a full referring URL.
 */
final readonly class NotFoundMonitor
{
    private const array BOT_PATTERNS = ['wp-', 'xmlrpc', '.php', '.env', '.git', 'cgi-bin', '/admin.', 'phpmyadmin', '.asp', '.sql', '.bak', 'vendor/', 'wlwmanifest'];

    private const array SEARCH_HOSTS = ['bing.com', 'duckduckgo.com', 'yahoo.', 'ecosia.org', 'search.brave.com'];

    public function __construct(private RedirectRules $rules) {}

    public function record(string $path, ?string $referrer): ?NotFoundLog
    {
        $path = RedirectRules::normalise($path);

        // Paths too long to be a real address, or ones a redirect now handles, are skipped.
        if (mb_strlen($path) > 190 || $this->rules->match($path) !== null) {
            return null;
        }

        [$kind, $from] = $this->classify($referrer);
        $now = now();

        return DB::transaction(function () use ($path, $kind, $from, $now): NotFoundLog {
            $log = NotFoundLog::query()->lockForUpdate()->firstOrNew(['path' => $path]);

            if (! $log->exists) {
                $log->first_seen_at = $now;
                $log->is_bot = self::looksLikeBot($path);
            }

            $log->hits++;
            $log->google_hits += $kind === 'google' ? 1 : 0;
            $log->referrer_kind = $kind;
            $log->referrer_path = $kind === 'internal' ? $from : $log->referrer_path;
            $log->last_seen_at = $now;

            // A resolved address that breaks again needs attention again.
            if ($log->status === 'resolved') {
                $log->status = 'open';
                $log->resolved_at = null;
            }

            $log->save();

            DB::table('not_found_hits')->upsert(
                [['not_found_log_id' => $log->id, 'day' => $now->toDateString(), 'hits' => 1, 'google_hits' => $kind === 'google' ? 1 : 0]],
                ['not_found_log_id', 'day'],
                ['hits' => DB::raw('hits + 1'), 'google_hits' => DB::raw('google_hits + '.($kind === 'google' ? 1 : 0))],
            );

            return $log;
        });
    }

    /**
     * The closest live address and a 0–100 likeness, if any is close enough.
     *
     * @param  list<string>|null  $known
     * @return array{path: string, score: int}|null
     */
    public function suggest(string $path, ?array $known = null): ?array
    {
        $known ??= $this->rules->knownPaths();
        $best = null;

        foreach ($known as $candidate) {
            if ($candidate === '/') {
                continue;
            }

            $score = self::likeness($path, $candidate);

            if ($best === null || $score > $best['score']) {
                $best = ['path' => $candidate, 'score' => $score];
            }
        }

        return $best !== null && $best['score'] >= 50 ? $best : null;
    }

    /**
     * Figma 21's four numbers.
     *
     * @return array{week: int, previous_week: int, change_percent: int|null, unique: int, from_google: int, fixed_this_month: int}
     */
    public function stats(): array
    {
        $today = Carbon::today();
        $week = (int) DB::table('not_found_hits')->where('day', '>', $today->copy()->subDays(7))->sum('hits');
        $previous = (int) DB::table('not_found_hits')->whereBetween('day', [$today->copy()->subDays(13), $today->copy()->subDays(7)])->sum('hits');

        return [
            'week' => $week,
            'previous_week' => $previous,
            'change_percent' => $previous === 0 ? null : (int) round(($week - $previous) / $previous * 100),
            'unique' => NotFoundLog::query()->where('status', 'open')->count(),
            'from_google' => (int) DB::table('not_found_hits')->join('not_found_logs', 'not_found_logs.id', '=', 'not_found_hits.not_found_log_id')
                ->where('not_found_logs.status', 'open')->where('day', '>', $today->copy()->subDays(7))->sum('not_found_hits.google_hits'),
            'fixed_this_month' => NotFoundLog::query()->where('status', 'resolved')->where('resolved_at', '>=', $today->copy()->startOfMonth())->count(),
        ];
    }

    /** @return array{0: string, 1: string|null} kind, linking site path */
    public function classify(?string $referrer): array
    {
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '') {
            return ['direct', null];
        }

        $host = mb_strtolower((string) preg_replace('/^www\./', '', $host));
        $site = parse_url((string) config('services.frontend.url'), PHP_URL_HOST);
        $site = is_string($site) ? mb_strtolower((string) preg_replace('/^www\./', '', $site)) : null;

        if ($site !== null && $host === $site) {
            $path = parse_url((string) $referrer, PHP_URL_PATH);

            return ['internal', is_string($path) ? mb_substr(RedirectRules::normalise($path), 0, 190) : '/'];
        }

        if (preg_match('/(^|\.)google\./', $host) === 1) {
            return ['google', null];
        }

        foreach (self::SEARCH_HOSTS as $engine) {
            if (str_contains($host, $engine)) {
                return ['search', null];
            }
        }

        return ['external', null];
    }

    public static function looksLikeBot(string $path): bool
    {
        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 0–100: does the missing address name the candidate page? Mostly whether
     * the candidate's last segment ("ocd" in /conditions/ocd) appears in it,
     * then overall word overlap, then raw edit distance. Words match when
     * equal or sharing a 4-letter stem ("anxiety" ~ "anxious").
     */
    public static function likeness(string $a, string $b): int
    {
        $words = fn (string $p): array => array_values(array_unique(array_filter(preg_split('/[^a-z0-9]+/', $p) ?: [], fn (string $w): bool => $w !== '' && ! in_array($w, ['html', 'htm', 'php', 'png', 'jpg'], true))));
        $similar = fn (string $x, string $y): bool => $x === $y || (strlen($x) >= 4 && strlen($y) >= 4 && substr($x, 0, 4) === substr($y, 0, 4));
        $found = fn (string $word, array $in): bool => array_filter($in, fn (string $other): bool => $similar($word, $other)) !== [];
        $wa = $words($a);
        $wb = $words($b);

        if ($wa === [] || $wb === []) {
            return 0;
        }

        $segment = $words((string) substr($b, (int) strrpos($b, '/')));
        $segmentCover = $segment === [] ? 0 : count(array_filter($segment, fn (string $w): bool => $found($w, $wa))) / count($segment);
        $matched = count(array_filter($wa, fn (string $w): bool => $found($w, $wb)));
        $overlap = $matched / max(1, count($wa) + count($wb) - $matched);
        $edit = 1 - levenshtein(substr($a, 0, 255), substr($b, 0, 255)) / max(strlen($a), strlen($b));

        return (int) round(max(0, min(1, 0.55 * $segmentCover + 0.25 * $overlap + 0.20 * $edit)) * 100);
    }
}
