<?php

declare(strict_types=1);

use Uptms\AuditLog\Models\AuditLog;
use Uptms\Auth\Services\Totp;
use Uptms\Permissions\Models\Role;

it('blocks admin routes until a required 2FA is set up', function () {
    $user = staffUser('admin', twoFactor: false);

    assertErrorCode(asAdmin($user)->getJson('/api/v1/admin/users'), 'TWO_FACTOR_SETUP_REQUIRED', 403);
    asAdmin($user)->getJson('/api/v1/auth/me')->assertOk();
});

it('lets a role that does not require 2FA in without it', function () {
    Role::query()->where('slug', 'admin')->update(['requires_two_factor' => false]);

    asAdmin(staffUser('admin', twoFactor: false))->getJson('/api/v1/admin/users')->assertOk();
});

it('sets up 2FA with a confirmed code and returns single-use recovery codes', function () {
    $user = staffUser('staff', twoFactor: false);
    $totp = new Totp;

    $secret = asAdmin($user)->postJson('/api/v1/auth/two-factor')->assertOk()
        ->assertJsonPath('data.otpauth_uri', fn (string $uri) => str_starts_with($uri, 'otpauth://totp/'))
        ->json('data.secret');

    assertErrorCode(asAdmin($user)->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000']), 'INVALID_TWO_FACTOR_CODE', 422);

    $codes = asAdmin($user)->postJson('/api/v1/auth/two-factor/confirm', ['code' => $totp->code($secret, $totp->timestep())])
        ->assertOk()->assertJsonCount(8, 'data.recovery_codes')->json('data.recovery_codes');

    expect($user->refresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->getRawOriginal('two_factor_secret'))->not->toBe($secret)
        ->and(AuditLog::query()->where('action', 'auth.two_factor_enabled')->exists())->toBeTrue();

    // A recovery code signs in once.
    asAdmin($user)->postJson('/api/v1/auth/logout');
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertJsonPath('data.two_factor', true);
    $this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => strtoupper($codes[0])])->assertOk();
    $this->postJson('/api/v1/auth/logout');
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);
    assertErrorCode($this->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => $codes[0]]), 'INVALID_TWO_FACTOR_CODE', 422);
});

it('does not let a required 2FA be switched off', function () {
    assertErrorCode(asAdmin(staffUser('staff'))->deleteJson('/api/v1/auth/two-factor', ['password' => 'password']), 'FORBIDDEN', 403);
});

it('switches off an optional 2FA with the password', function () {
    Role::query()->where('slug', 'staff')->update(['requires_two_factor' => false]);
    $user = staffUser('staff');

    asAdmin($user)->deleteJson('/api/v1/auth/two-factor', ['password' => 'password'])->assertOk();

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('regenerates recovery codes with the password', function () {
    $user = staffUser('staff');

    asAdmin($user)->postJson('/api/v1/auth/two-factor/recovery-codes', ['password' => 'password'])
        ->assertOk()->assertJsonCount(8, 'data.recovery_codes');
    asAdmin($user)->getJson('/api/v1/auth/two-factor')->assertOk()->assertJsonPath('data.recovery_codes_left', 8);
});

it('needs the current password for security changes', function (string $method, string $uri, array $payload, string $field) {
    asAdmin(staffUser('staff'))->json($method, $uri, $payload)->assertStatus(422)->assertJsonValidationErrors([$field]);
})->with([
    'change password' => ['PUT', '/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'], 'current_password'],
    'turn off 2FA' => ['DELETE', '/api/v1/auth/two-factor', ['password' => 'wrong'], 'password'],
    'new recovery codes' => ['POST', '/api/v1/auth/two-factor/recovery-codes', ['password' => 'wrong'], 'password'],
]);

it('changes the password', function () {
    $user = staffUser('staff');

    asAdmin($user)->putJson('/api/v1/auth/password', [
        'current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect(AuditLog::query()->where('action', 'auth.password_changed')->exists())->toBeTrue();
});
