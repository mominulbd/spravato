<?php

declare(strict_types=1);

use Uptms\Pages\PagesServiceProvider;

return [
    'id' => 'uptms.pages',
    'name' => 'Pages',
    'description' => 'Pages built from the website’s section blocks (Figma 06–07): working copy with autosave, revisions, publish and schedule, signed previews and the public page API.',
    'autoload' => [
        'Uptms\\Pages\\' => 'src/',
        'Uptms\\Pages\\Seeders\\' => 'seeders/',
    ],
    'provider' => PagesServiceProvider::class,
    'require' => ['uptms.publishing', 'uptms.media', 'uptms.seo', 'uptms.audit_log', 'uptms.permissions'],
];
