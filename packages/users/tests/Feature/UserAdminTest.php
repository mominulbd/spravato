<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;
use Uptms\Users\Notifications\InvitationNotification;

function roleId(string $slug): int
{
    return (int) Role::query()->where('slug', $slug)->value('id');
}

it('invites a user who has no password until they accept', function () {
    Notification::fake();

    $id = asAdmin(staffUser('admin'))->postJson('/api/v1/admin/users', [
        'name' => 'Dana Osei', 'email' => 'Dana@uptms.test', 'role_id' => roleId('staff'),
    ])->assertCreated()
        ->assertJsonPath('data.status', 'invited')
        ->assertJsonPath('data.email', 'dana@uptms.test')
        ->assertJsonPath('data.role.slug', 'staff')
        ->assertJsonPath('data.two_factor_enabled', false)
        ->json('data.id');

    $user = User::query()->findOrFail($id);
    expect($user->password)->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.invited')->where('auditable_id', (string) $id)->exists())->toBeTrue();
    Notification::assertSentTo($user, InvitationNotification::class);
});

it('lists users with search, status filter and tab counts', function () {
    $admin = staffUser('admin', ['name' => 'Web partner']);
    staffUser('staff', ['name' => 'Joy Kiongo']);
    staffUser('staff', ['name' => 'Dana Osei', 'password' => null]);

    asAdmin($admin)->getJson('/api/v1/admin/users?search=joy')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Joy Kiongo');
    asAdmin($admin)->getJson('/api/v1/admin/users?filter[status]=invited')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.counts.all', 3)
        ->assertJsonPath('meta.counts.invited', 1);
    asAdmin($admin)->getJson('/api/v1/admin/users?sort=-name&per_page=2')->assertOk()
        ->assertJsonPath('data.0.name', 'Web partner')->assertJsonPath('meta.last_page', 2);
    asAdmin($admin)->getJson('/api/v1/admin/users?sort=password')->assertStatus(422);
});

it('stops an Admin from making or touching Super Admins', function () {
    Notification::fake();
    $admin = staffUser('admin');
    $boss = superAdmin();

    assertErrorCode(asAdmin($admin)->postJson('/api/v1/admin/users', [
        'name' => 'X', 'email' => 'x@uptms.test', 'role_id' => roleId('super_admin'),
    ]), 'FORBIDDEN', 403)->assertJsonPath('errors.role_id.0', 'Only a Super Admin can assign this role.');

    assertErrorCode(asAdmin($admin)->putJson("/api/v1/admin/users/{$boss->id}", [
        'name' => 'X', 'email' => $boss->email, 'role_id' => roleId('staff'), 'is_active' => true,
    ]), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($admin)->deleteJson("/api/v1/admin/users/{$boss->id}"), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($admin)->postJson("/api/v1/admin/users/{$boss->id}/two-factor/reset"), 'FORBIDDEN', 403);
});

it('stops anyone handing out a role with permissions they lack', function () {
    $custom = Role::query()->create(['slug' => 'reviewer', 'name' => 'Reviewer']);
    $custom->permissions()->attach(DB::table('permissions')->whereIn('key', ['admin.access', 'content.review'])->pluck('id'));

    assertErrorCode(asAdmin(staffUser('admin'))->postJson('/api/v1/admin/users', [
        'name' => 'X', 'email' => 'x@uptms.test', 'role_id' => $custom->id,
    ]), 'FORBIDDEN', 403)->assertJsonPath('errors.role_id.0', 'The Reviewer role grants content.review.');
});

it('changes a role and audits it as a permission change', function () {
    $joy = staffUser('staff');

    asAdmin(staffUser('admin'))->putJson("/api/v1/admin/users/{$joy->id}", [
        'name' => $joy->name, 'email' => $joy->email, 'role_id' => roleId('manager'), 'is_active' => true,
    ])->assertOk()->assertJsonPath('data.role.slug', 'manager');

    expect(AuditLog::query()->where('action', 'permission.changed')->value('new_values'))->toBe(['role' => 'manager']);
});

it('does not let anyone demote or deactivate themselves', function () {
    $admin = staffUser('admin');

    assertErrorCode(asAdmin($admin)->putJson("/api/v1/admin/users/{$admin->id}", [
        'name' => $admin->name, 'email' => $admin->email, 'role_id' => roleId('staff'), 'is_active' => true,
    ]), 'FORBIDDEN', 403);
});

it('does not let anyone deactivate or remove themselves', function () {
    $admin = staffUser('admin');

    assertErrorCode(asAdmin($admin)->putJson("/api/v1/admin/users/{$admin->id}", [
        'name' => $admin->name, 'email' => $admin->email, 'role_id' => roleId('admin'), 'is_active' => false,
    ]), 'FORBIDDEN', 403);
});

it('refuses to remove your own account', function () {
    $admin = staffUser('admin');

    assertErrorCode(asAdmin($admin)->deleteJson("/api/v1/admin/users/{$admin->id}"), 'FORBIDDEN', 403);
});

it('deactivates a user and ends their sessions', function () {
    $joy = staffUser('staff');
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $joy->id, 'payload' => '', 'last_activity' => time()]);

    asAdmin(staffUser('admin'))->putJson("/api/v1/admin/users/{$joy->id}", [
        'name' => $joy->name, 'email' => $joy->email, 'role_id' => roleId('staff'), 'is_active' => false,
    ])->assertOk()->assertJsonPath('data.status', 'deactivated');

    expect(DB::table('sessions')->where('user_id', $joy->id)->exists())->toBeFalse();
});

it('soft-deletes a user and keeps their audit trail', function () {
    $joy = staffUser('staff');

    asAdmin(staffUser('admin'))->deleteJson("/api/v1/admin/users/{$joy->id}")->assertOk();

    expect(User::query()->find($joy->id))->toBeNull()
        ->and(User::withTrashed()->find($joy->id))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.deleted')->exists())->toBeTrue();
    assertErrorCode(asAdmin(superAdmin())->getJson("/api/v1/admin/users/{$joy->id}"), 'NOT_FOUND', 404);
});

it('resets a lost 2FA so it is set up again', function () {
    $joy = staffUser('staff');

    asAdmin(staffUser('admin'))->postJson("/api/v1/admin/users/{$joy->id}/two-factor/reset")
        ->assertOk()->assertJsonPath('data.two_factor_enabled', false);
    expect(AuditLog::query()->where('action', 'user.two_factor_reset')->exists())->toBeTrue();
});

it('never exposes password hashes or 2FA secrets', function () {
    $joy = staffUser('staff');

    $json = asAdmin(superAdmin())->getJson("/api/v1/admin/users/{$joy->id}")->assertOk()->getContent();

    expect($json)->not->toContain('password')->not->toContain('$2y$')->not->toContain('two_factor_secret')->not->toContain('recovery');
});

it('denies the users API to roles without users.manage', function (string $role) {
    $target = staffUser('staff');

    assertErrorCode(asAdmin(staffUser($role))->getJson('/api/v1/admin/users'), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin(staffUser($role))->deleteJson("/api/v1/admin/users/{$target->id}"), 'FORBIDDEN', 403);
})->with(['manager', 'staff']);
