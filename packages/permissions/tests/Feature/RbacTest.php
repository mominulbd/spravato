<?php

declare(strict_types=1);

use Uptms\AuditLog\Models\AuditLog;
use Uptms\Permissions\Models\Permission;
use Uptms\Permissions\Models\Role;
use Uptms\Permissions\Services\PermissionRegistry;

/*
 * The signed-off matrix (Figma screen 26). Staff see only their assigned
 * requests (no consultations.view_all); Admins manage users but not Super
 * Admins (enforced in StaffGuard) and cannot edit role permissions.
 */
const MATRIX = [
    //                         super_admin admin manager staff
    'admin.access' => [true, true, true, true],
    'consultations.view' => [true, true, true, true],
    'consultations.view_all' => [true, true, true, false],
    'consultations.update' => [true, true, true, true],
    'consultations.export' => [true, true, false, false],
    'content.edit' => [true, true, true, true],
    'content.publish' => [true, true, true, false],
    'content.review' => [true, false, false, false],
    'media.manage' => [true, true, true, false],
    'seo.edit' => [true, true, true, false],
    'seo.manage' => [true, true, false, false],
    'settings.manage' => [true, true, false, false],
    'users.manage' => [true, true, false, false],
    'roles.manage' => [true, false, false, false],
    'audit.view' => [true, true, false, false],
];

it('seeds every registry permission and the four built-in roles', function () {
    expect(Permission::query()->pluck('key')->sort()->values()->all())->toBe(collect(app(PermissionRegistry::class)->keys())->sort()->values()->all())
        ->and(array_keys(MATRIX))->toEqualCanonicalizing(app(PermissionRegistry::class)->keys())
        ->and(Role::query()->where('is_system', true)->pluck('slug')->sort()->values()->all())->toBe(['admin', 'manager', 'staff', 'super_admin']);
});

it('grants each role exactly the Figma matrix', function (string $permission, int $column, string $role) {
    $user = staffUser($role);

    expect($user->hasPermission($permission))->toBe(MATRIX[$permission][$column])
        ->and($user->can($permission))->toBe(MATRIX[$permission][$column]);
})->with(array_keys(MATRIX))->with([
    'super admin' => [0, 'super_admin'],
    'admin' => [1, 'admin'],
    'manager' => [2, 'manager'],
    'staff' => [3, 'staff'],
]);

it('enforces each guarded endpoint per role', function (string $method, string $uri, array $allowed) {
    foreach (['super_admin', 'admin', 'manager', 'staff'] as $role) {
        $response = asAdmin(staffUser($role))->json($method, $uri);

        in_array($role, $allowed, true)
            ? expect($response->status())->toBe(200, "{$role} should reach {$method} {$uri}")
            : assertErrorCode($response, 'FORBIDDEN', 403);
    }
})->with([
    'users list' => ['GET', '/api/v1/admin/users', ['super_admin', 'admin']],
    'roles list' => ['GET', '/api/v1/admin/roles', ['super_admin', 'admin']],
    'permission registry' => ['GET', '/api/v1/admin/permissions', ['super_admin', 'admin']],
    'audit log' => ['GET', '/api/v1/admin/audit-logs', ['super_admin', 'admin']],
    'require 2FA' => ['POST', '/api/v1/admin/roles/require-two-factor', ['super_admin']],
]);

it('keeps a user without admin.access out of the admin', function () {
    $role = Role::query()->create(['slug' => 'locked', 'name' => 'Locked', 'requires_two_factor' => false]);
    $user = staffUser('locked');

    assertErrorCode(asAdmin($user)->getJson('/api/v1/admin/users'), 'FORBIDDEN', 403)
        ->assertJsonPath('message', 'This action requires the admin.access permission.');
    expect($role->users()->count())->toBe(1);
});

it('creates a custom role and audits its permissions', function () {
    asAdmin(superAdmin())->postJson('/api/v1/admin/roles', [
        'name' => 'Front desk', 'slug' => 'front_desk', 'requires_two_factor' => true,
        'permissions' => ['admin.access', 'consultations.view'],
    ])->assertCreated()
        ->assertJsonPath('data.permissions', ['admin.access', 'consultations.view'])
        ->assertJsonPath('data.users_count', 0);

    expect(AuditLog::query()->where('action', 'permission.changed')->value('new_values'))
        ->toBe(['permissions' => ['admin.access', 'consultations.view']]);
});

it('rejects unknown permission keys', function () {
    asAdmin(superAdmin())->postJson('/api/v1/admin/roles', [
        'name' => 'X', 'slug' => 'x', 'requires_two_factor' => true, 'permissions' => ['pages.fly'],
    ])->assertStatus(422)->assertJsonValidationErrors(['permissions.0']);
});

it('updates a built-in role but never the Super Admin role', function () {
    $admin = asAdmin(superAdmin());
    $staff = Role::query()->where('slug', 'staff')->firstOrFail();
    $super = Role::query()->where('slug', 'super_admin')->firstOrFail();

    $admin->putJson("/api/v1/admin/roles/{$staff->id}", [
        'name' => 'Staff', 'requires_two_factor' => true, 'permissions' => ['admin.access', 'consultations.view'],
    ])->assertOk()->assertJsonPath('data.permissions', ['admin.access', 'consultations.view']);

    assertErrorCode($admin->putJson("/api/v1/admin/roles/{$super->id}", [
        'name' => 'Boss', 'requires_two_factor' => false, 'permissions' => [],
    ]), 'FORBIDDEN', 403);
});

it('protects built-in roles and roles in use from deletion', function () {
    $admin = asAdmin(superAdmin());
    $custom = Role::query()->create(['slug' => 'temp', 'name' => 'Temp']);
    staffUser('temp');

    assertErrorCode($admin->deleteJson('/api/v1/admin/roles/'.Role::query()->where('slug', 'staff')->value('id')), 'FORBIDDEN', 403);
    assertErrorCode($admin->deleteJson("/api/v1/admin/roles/{$custom->id}"), 'CONFLICT', 409);
});

it('requires 2FA for every role that can view requests', function () {
    Role::query()->update(['requires_two_factor' => false]);
    Role::query()->create(['slug' => 'web', 'name' => 'Web only', 'requires_two_factor' => false]);

    asAdmin(superAdmin())->postJson('/api/v1/admin/roles/require-two-factor')
        ->assertOk()->assertJsonPath('data.updated', ['super_admin', 'admin', 'manager', 'staff']);

    expect(Role::query()->where('slug', 'web')->value('requires_two_factor'))->toBeFalse();
});

it('lists the matrix grouped as on Figma 26', function () {
    asAdmin(staffUser('admin'))->getJson('/api/v1/admin/permissions')
        ->assertOk()
        ->assertJsonPath('data.groups.0.label', 'Consultation requests')
        ->assertJsonPath('data.groups.0.permissions.0.label', 'View requests');
});
