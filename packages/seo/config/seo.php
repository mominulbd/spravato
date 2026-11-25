<?php

declare(strict_types=1);

return [
    // Search engines are notified (IndexNow, Search Console sitemap submit)
    // only when the site is live. Keep false on staging; the website's own
    // ALLOW_INDEXING switch must match.
    'live' => (bool) env('SEO_LIVE', false),

    // Google Search Console: a service account's JSON key (file path or the
    // JSON itself) with access to the property, e.g. "sc-domain:uptms.com".
    'search_console' => [
        'credentials' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS'),
        'property' => env('GOOGLE_SEARCH_CONSOLE_PROPERTY'),
    ],

    // Chrome UX Report API key (Google Cloud, no billing needed) for the Core
    // Web Vitals card of the SEO overview. Unset: the card says so.
    'crux_key' => env('CRUX_API_KEY'),

    // The nightly site audit runs at 6:00 in the clinic's time zone.
    'timezone' => env('CLINIC_TIMEZONE', 'America/Los_Angeles'),
];
