<?php

declare(strict_types=1);

use Uptms\Collections\CollectionsServiceProvider;

return [
    'id' => 'uptms.collections',
    'name' => 'Collections',
    'description' => 'Reusable content shown across the website (Figma 14–15): care team, FAQs, testimonials, insurance partners, trust points and journey steps.',
    'autoload' => [
        'Uptms\\Collections\\' => 'src/',
        'Uptms\\Collections\\Seeders\\' => 'seeders/',
    ],
    'provider' => CollectionsServiceProvider::class,
    'require' => ['uptms.publishing', 'uptms.media', 'uptms.audit_log', 'uptms.permissions'],
];
