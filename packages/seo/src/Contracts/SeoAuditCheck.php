<?php

declare(strict_types=1);

namespace Uptms\Seo\Contracts;

use Uptms\Seo\Domain\SeoIssue;
use Uptms\Seo\Domain\SeoSubject;

/**
 * One part of the nightly site audit (Figma 19). The SEO module checks
 * metadata itself; other modules register checks for what only they know
 * (404s still visited, overdue clinical reviews) with SeoAuditChecks.
 */
interface SeoAuditCheck
{
    /**
     * @param  list<SeoSubject>  $subjects  every published page, post and condition
     * @return iterable<SeoIssue>
     */
    public function issues(array $subjects): iterable;
}
