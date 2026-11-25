<?php

declare(strict_types=1);

use Uptms\Settings\SettingsServiceProvider;

return [
    'id' => 'uptms.settings',
    'name' => 'Settings',
    'description' => 'Clinic settings (Figma 24): name, address and phone, office hours and closures, announcement bar, disclaimers, privacy and retention.',
    'autoload' => [
        'Uptms\\Settings\\' => 'src/',
        'Uptms\\Settings\\Seeders\\' => 'seeders/',
    ],
    'provider' => SettingsServiceProvider::class,
    'require' => ['uptms.publishing', 'uptms.audit_log', 'uptms.permissions'],
];
