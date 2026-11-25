<?php

declare(strict_types=1);

use Uptms\Media\MediaServiceProvider;

return [
    'id' => 'uptms.media',
    'name' => 'Media',
    'description' => 'Media library: validated uploads with metadata stripped, WebP/AVIF variants, focal point, usage tracking and a 30-day trash.',
    'autoload' => [
        'Uptms\\Media\\' => 'src/',
        'Uptms\\Media\\Factories\\' => 'factories/',
    ],
    'provider' => MediaServiceProvider::class,
    'require' => ['uptms.publishing', 'uptms.audit_log', 'uptms.permissions'],
];
