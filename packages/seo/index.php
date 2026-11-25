<?php

declare(strict_types=1);

use Uptms\Seo\SeoServiceProvider;

return [
    'id' => 'uptms.seo',
    'name' => 'SEO',
    'description' => 'Search and social metadata for every page, post and condition (Figma 08–10), site-wide SEO settings (Figma 23), the SEO checklist, the JSON-LD graph and the nightly site audit (Figma 19), to which other modules add checks through SeoAuditChecks. Content modules describe themselves through SeoSources; this module knows none of them.',
    'autoload' => [
        'Uptms\\Seo\\' => 'src/',
    ],
    'provider' => SeoServiceProvider::class,
    'require' => ['uptms.publishing', 'uptms.media', 'uptms.settings', 'uptms.audit_log', 'uptms.permissions'],
];
