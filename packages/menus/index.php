<?php

declare(strict_types=1);

use Uptms\Menus\MenusServiceProvider;

return [
    'id' => 'uptms.menus',
    'name' => 'Menus',
    'description' => 'Navigation menus (Figma 17): header, mobile menu, section navigation and footer lists, linking to pages by id.',
    'autoload' => [
        'Uptms\\Menus\\' => 'src/',
        'Uptms\\Menus\\Seeders\\' => 'seeders/',
    ],
    'provider' => MenusServiceProvider::class,
    'require' => ['uptms.publishing', 'uptms.pages', 'uptms.audit_log', 'uptms.permissions'],
];
