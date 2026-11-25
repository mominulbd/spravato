<?php

declare(strict_types=1);

/*
 * Module registry (aminulbd/laravel-packages). Every domain module is a
 * package under /packages with an index.php manifest. A module is live only
 * when its id appears in `enabled`; disabling one removes its routes,
 * migrations, commands and bindings without touching code.
 *
 * Order here is documentation only — packages are registered in directory
 * order and must not depend on registration order (bindings resolve lazily).
 * The planned catalogue is in docs/ARCHITECTURE.md §3.
 */
return [
    'roots' => [
        'default' => [
            'forced' => false,
            'location' => '/packages',
        ],
    ],

    'enabled' => [
        // Modules are added here phase by phase (docs/ROADMAP.md).

        // Phase 1 — access & admin shell
        'uptms.audit_log', 'uptms.permissions', 'uptms.users', 'uptms.auth',

        // Phase 2 — content & public site
        'uptms.publishing', 'uptms.media', 'uptms.settings', 'uptms.collections', 'uptms.pages', 'uptms.menus',

        // Phase 3 — consultation requests
        'uptms.consultations',

        // Phase 4 — posts & SEO
        'uptms.seo',
        'uptms.redirects',
    ],
];
