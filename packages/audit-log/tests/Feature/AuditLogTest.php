<?php

declare(strict_types=1);

use Uptms\AuditLog\Models\AuditLog;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Permissions\Models\Role;

it('records who, what and from which request, redacting secrets', function () {
    $user = staffUser('admin');
    $this->actingAs($user);
    $role = Role::query()->where('slug', 'staff')->firstOrFail();

    $log = app(AuditRecorder::class)->record('role.checked', $role, ['a' => 1], ['a' => 2, 'password' => 'x', 'nested' => ['two_factor_secret' => 'y']]);

    expect($log?->user_id)->toBe($user->id)
        ->and($log?->auditable_type)->toBe('role')
        ->and($log?->auditable_label)->toBe('staff')
        ->and($log?->new_values)->toBe(['a' => 2, 'password' => '[redacted]', 'nested' => ['two_factor_secret' => '[redacted]']]);
});

it('records model changes as diffs', function () {
    $role = Role::query()->create(['slug' => 'temp', 'name' => 'Old']);
    $role->update(['name' => 'New']);

    $log = AuditLog::query()->where('action', 'role.updated')->sole();
    expect($log->old_values)->toBe(['name' => 'Old'])->and($log->new_values)->toBe(['name' => 'New']);
});

it('is append-only', function () {
    app(AuditRecorder::class)->record('x.y')?->update(['action' => 'changed']);
})->throws(LogicException::class);

it('filters by category, actor, search and date, newest first', function () {
    $joy = staffUser('staff', ['name' => 'Joy Kiongo']);
    $recorder = app(AuditRecorder::class);
    $recorder->record('auth.login', $joy, userId: $joy->id);
    $recorder->record('permission.changed', $joy, label: 'Joy Kiongo');
    $this->travel(-10)->days();
    $recorder->record('auth.login_failed', null, userId: $joy->id);
    $this->travelBack();

    $admin = asAdmin(staffUser('admin'));
    $admin->getJson('/api/v1/admin/audit-logs?filter[category]=sign_in&filter[user_id]='.$joy->id)
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.action', 'auth.login')
        ->assertJsonPath('data.0.user.name', 'Joy Kiongo');
    $admin->getJson('/api/v1/admin/audit-logs?filter[from]='.now()->subDays(7)->toDateString().'&filter[user_id]='.$joy->id)
        ->assertOk()->assertJsonCount(1, 'data');
    $admin->getJson('/api/v1/admin/audit-logs?search=permission')->assertOk()->assertJsonCount(1, 'data');
    $admin->getJson('/api/v1/admin/audit-logs?filter[category]=bogus')->assertStatus(422);
});

it('exports the filtered log as CSV and audits the export', function () {
    app(AuditRecorder::class)->record('auth.login', label: '=HYPERLINK("x")');

    $csv = asAdmin(staffUser('admin'))->get('/api/v1/admin/audit-logs/export?filter[category]=sign_in')
        ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

    expect($csv)->toContain('auth.login')->toContain("'=HYPERLINK")
        ->and(AuditLog::query()->where('action', 'audit.exported')->exists())->toBeTrue();
});

it('needs audit.view', function (string $role) {
    assertErrorCode(asAdmin(staffUser($role))->getJson('/api/v1/admin/audit-logs'), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin(staffUser($role))->getJson('/api/v1/admin/audit-logs/export'), 'FORBIDDEN', 403);
})->with(['manager', 'staff']);
