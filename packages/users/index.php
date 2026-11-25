<?php

declare(strict_types=1);

use Uptms\Users\UsersServiceProvider;

return [
    'id' => 'uptms.users',
    'name' => 'Users',
    'description' => 'Staff accounts: one role each, invitations, activation, soft delete.',
    'autoload' => [
        'Uptms\\Users\\' => 'src/',
        'Uptms\\Users\\Factories\\' => 'factories/',
    ],
    'provider' => UsersServiceProvider::class,
    'require' => ['uptms.permissions', 'uptms.audit_log'],
];
