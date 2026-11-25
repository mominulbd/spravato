<?php

declare(strict_types=1);

use Uptms\Auth\AuthServiceProvider;

return [
    'id' => 'uptms.auth',
    'name' => 'Auth',
    'description' => 'Session sign-in with TOTP two-factor, password reset, /auth/me, 2FA enforcement per role behind the site-wide Settings → Security switch.',
    'autoload' => [
        'Uptms\\Auth\\' => 'src/',
    ],
    'provider' => AuthServiceProvider::class,
    'require' => ['uptms.permissions', 'uptms.audit_log', 'uptms.users', 'uptms.settings'],
];
