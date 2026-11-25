<?php

declare(strict_types=1);

namespace Uptms\Redirects\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Uptms\Redirects\Models\NotFoundLog;

/**
 * Keeps the redirect and 404 tables from growing without limit. The screens
 * read 30 days of daily counts at most, so daily rows go after 90 days, and
 * a missing address nobody has tried for 180 days is forgotten (it is
 * logged again if it comes back). Redirect totals are kept. Runs daily.
 */
final class PruneRedirectStatsCommand extends Command
{
    public const int DAILY_DAYS = 90;

    public const int NOT_FOUND_DAYS = 180;

    protected $signature = 'redirects:prune';

    protected $description = 'Delete old daily redirect and 404 counts, and 404 addresses not seen for months';

    public function handle(): int
    {
        $cutoff = now()->subDays(self::DAILY_DAYS)->toDateString();
        $hits = DB::table('redirect_hits')->where('day', '<', $cutoff)->delete();
        $hits += DB::table('not_found_hits')->where('day', '<', $cutoff)->delete();
        $logs = NotFoundLog::query()->where('last_seen_at', '<', now()->subDays(self::NOT_FOUND_DAYS))->delete();

        $this->info("Deleted {$hits} daily rows and {$logs} old 404 addresses.");

        return self::SUCCESS;
    }
}
