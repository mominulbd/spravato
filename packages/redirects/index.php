<?php

declare(strict_types=1);

use Uptms\Redirects\RedirectsServiceProvider;

return [
    'id' => 'uptms.redirects',
    'name' => 'Redirects & 404 monitor',
    'description' => 'Redirects the website applies before rendering (Figma 20) — added by hand, imported, or automatically when a live page moves — and the 404 monitor (Figma 21): addresses visitors tried that do not exist, with suggested targets. No IP addresses are stored.',
    'autoload' => [
        'Uptms\\Redirects\\' => 'src/',
    ],
    'provider' => RedirectsServiceProvider::class,
    'require' => ['uptms.publishing', 'uptms.seo', 'uptms.audit_log', 'uptms.permissions'],
];
