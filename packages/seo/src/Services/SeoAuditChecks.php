<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Uptms\Seo\Contracts\SeoAuditCheck;

/** Registry of the site audit's checks, filled by module providers. */
final class SeoAuditChecks
{
    /** @var array<string, class-string<SeoAuditCheck>> */
    private array $checks = [];

    /** @param class-string<SeoAuditCheck> $check */
    public function register(string $name, string $check): void
    {
        $this->checks[$name] = $check;
    }

    /** @return list<SeoAuditCheck> */
    public function all(): array
    {
        return array_values(array_map(fn (string $check): SeoAuditCheck => app($check), $this->checks));
    }
}
