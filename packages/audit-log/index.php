<?php

declare(strict_types=1);

use Uptms\AuditLog\AuditLogServiceProvider;

return [
    'id' => 'uptms.audit_log',
    'name' => 'Audit log',
    'description' => 'Append-only record of sign-ins and sensitive actions: who, what, before/after and from which request.',
    'autoload' => [
        'Uptms\\AuditLog\\' => 'src/',
    ],
    'provider' => AuditLogServiceProvider::class,
];
