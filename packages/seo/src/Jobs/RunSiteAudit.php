<?php

declare(strict_types=1);

namespace Uptms\Seo\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Uptms\Seo\Models\SeoAudit;
use Uptms\Seo\Services\SiteAudit;

/** "Run audit now" (Figma 19): the run is queued; the overview polls until it is done. */
final class RunSiteAudit implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $auditId) {}

    public function handle(SiteAudit $audit): void
    {
        $row = SeoAudit::query()->find($this->auditId);

        if ($row !== null && $row->status === 'running') {
            $audit->run($row);
        }
    }
}
