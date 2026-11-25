<?php

declare(strict_types=1);

namespace Uptms\Seo\Console\Commands;

use Illuminate\Console\Command;
use Uptms\Seo\Services\SiteAudit;

/** The nightly site audit (6:00 clinic time) behind the SEO overview. */
final class RunSiteAuditCommand extends Command
{
    protected $signature = 'seo:audit';

    protected $description = 'Check every published URL and record the site’s SEO health';

    public function handle(SiteAudit $audit): int
    {
        $run = $audit->run($audit->start('schedule'));
        $open = count(array_filter($run->issues ?? [], fn (array $i): bool => SiteAudit::open($i['severity'])));
        $this->info("Score {$run->score}; {$open} open issue kinds across {$run->urls} URLs.");

        return self::SUCCESS;
    }
}
