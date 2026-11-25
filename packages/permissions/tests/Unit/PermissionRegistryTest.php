<?php

declare(strict_types=1);

use Uptms\Permissions\Services\PermissionRegistry;

it('uses {module}.{action} keys, each listed once', function () {
    $keys = (new PermissionRegistry)->keys();

    expect($keys)->toEqual(array_values(array_unique($keys)))
        ->each->toMatch('/^[a-z_]+\.[a-z_]+$/');
});

it('grants built-in roles only registered keys', function () {
    $registry = new PermissionRegistry;

    foreach ($registry->roles() as $role) {
        expect(array_diff($registry->expand($role['grants']), $registry->keys()))->toBe([]);
    }

    expect($registry->expand(['*']))->toBe($registry->keys());
});
