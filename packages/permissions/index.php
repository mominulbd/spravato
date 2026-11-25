<?php

declare(strict_types=1);

use Uptms\Permissions\PermissionsServiceProvider;

return [
    'id' => 'uptms.permissions',
    'name' => 'Permissions',
    'description' => 'Roles, the {module}.{action} permission registry, the permission middleware and the Super Admin bypass.',
    'autoload' => [
        'Uptms\\Permissions\\' => 'src/',
        'Uptms\\Permissions\\Seeders\\' => 'seeders/',
    ],
    'provider' => PermissionsServiceProvider::class,
    'require' => ['uptms.audit_log'],
];
