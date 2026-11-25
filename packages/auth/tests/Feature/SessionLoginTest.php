<?php

declare(strict_types=1);

use Uptms\AuditLog\Models\AuditLog;
use Uptms\Auth\Services\Totp;
use Uptms\Users\Factories\UserFactory;

function currentTotp(int $offsetSteps = 0): string
{
    $totp = new Totp;

    return $totp->code(UserFactory::TWO_FACTOR_SECRET, $totp->timestep() + $offsetSteps);
}

it('signs in without 2FA and returns the current user with permissions', function () {
    $user = staffUser('manager', ['email' => 'cathy@uptms.test'], twoFactor: false);

    $this->postJson('/api/v1/auth/login', ['email' => 'Cathy@uptms.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.two_factor', false)
        ->assertJsonPath('data.user.email', 'cathy@uptms.test')
        ->assertJsonPath('data.user.role.slug', 'manager')
        ->assertJsonPath('data.user.two_factor.required', true)
        ->assertJsonPath('data.user.two_factor.enabled', false);

    $this->assertAuthenticatedAs($user);
    expect(AuditLog::query()->where('action', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($user->refresh()->last_login_at)->not->toBeNull();
});

it('asks for a TOTP code before creating a session when 2FA is on', function () {
    $user = staffUser('staff', ['email' => 'joy@uptms.test']);

    $this->postJson('/api/v1/auth/login', ['email' => 'joy@uptms.test', 'password' => 'password'])
        ->assertOk()->assertJsonPath('data.two_factor', true);
    $this->assertGuest();

    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => currentTotp()])
        ->assertOk()->assertJsonPath('data.user.email', 'joy@uptms.test');
    $this->assertAuthenticatedAs($user);

    expect(AuditLog::query()->where('action', 'auth.login')->value('new_values'))->toBe(['method' => 'totp']);
});

it('rejects a wrong or replayed TOTP code', function () {
    staffUser('staff', ['email' => 'joy@uptms.test']);
    $this->postJson('/api/v1/auth/login', ['email' => 'joy@uptms.test', 'password' => 'password']);

    assertErrorCode($this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000']), 'INVALID_TWO_FACTOR_CODE', 422);
    $this->assertGuest();

    $code = currentTotp();
    $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $code])->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertOk();

    $this->postJson('/api/v1/auth/login', ['email' => 'joy@uptms.test', 'password' => 'password']);
    assertErrorCode($this->postJson('/api/v1/auth/two-factor-challenge', ['code' => $code]), 'INVALID_TWO_FACTOR_CODE', 422);
    expect(AuditLog::query()->where('action', 'auth.two_factor_failed')->count())->toBe(2);
});

it('refuses a challenge without a pending sign-in', function () {
    assertErrorCode($this->postJson('/api/v1/auth/two-factor-challenge', ['code' => currentTotp()]), 'UNAUTHENTICATED', 401);
});

it('refuses wrong credentials without saying which part was wrong, and logs the attempt', function () {
    $user = staffUser('staff', ['email' => 'joy@uptms.test']);

    assertErrorCode($this->postJson('/api/v1/auth/login', ['email' => 'joy@uptms.test', 'password' => 'nope']), 'INVALID_CREDENTIALS', 422);
    assertErrorCode($this->postJson('/api/v1/auth/login', ['email' => 'nobody@uptms.test', 'password' => 'nope']), 'INVALID_CREDENTIALS', 422);
    $this->assertGuest();

    expect(AuditLog::query()->where('action', 'auth.login_failed')->pluck('user_id')->all())->toBe([$user->id, null]);
});

it('refuses invited accounts that have no password yet', function () {
    staffUser('staff', ['email' => 'dana@uptms.test', 'password' => null]);

    assertErrorCode($this->postJson('/api/v1/auth/login', ['email' => 'dana@uptms.test', 'password' => '']), 'VALIDATION_FAILED', 422);
    assertErrorCode($this->postJson('/api/v1/auth/login', ['email' => 'dana@uptms.test', 'password' => 'anything']), 'INVALID_CREDENTIALS', 422);
});

it('refuses a deactivated account only after the right password', function () {
    staffUser('staff', ['email' => 'gone@uptms.test', 'is_active' => false]);

    assertErrorCode($this->postJson('/api/v1/auth/login', ['email' => 'gone@uptms.test', 'password' => 'password']), 'ACCOUNT_INACTIVE', 403);
    $this->assertGuest();
});

it('throttles sign-in attempts per email and IP', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'x@uptms.test', 'password' => 'nope']);
    }

    assertErrorCode($this->postJson('/api/v1/auth/login', ['email' => 'x@uptms.test', 'password' => 'nope']), 'RATE_LIMITED', 429);
});

it('throttles two-factor guesses', function () {
    staffUser('staff', ['email' => 'joy@uptms.test']);
    $this->postJson('/api/v1/auth/login', ['email' => 'joy@uptms.test', 'password' => 'password']);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000']);
    }

    assertErrorCode($this->postJson('/api/v1/auth/two-factor-challenge', ['code' => currentTotp()]), 'RATE_LIMITED', 429);
});

it('signs out and records it', function () {
    $user = staffUser();

    $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertOk();
    $this->assertGuest('web');
    expect(AuditLog::query()->where('action', 'auth.logout')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('requires a session for me', function () {
    assertErrorCode($this->getJson('/api/v1/auth/me'), 'UNAUTHENTICATED', 401);
});

it('lists every registry key for a Super Admin', function () {
    $keys = $this->actingAs(superAdmin())->getJson('/api/v1/auth/me')
        ->assertOk()->assertJsonPath('data.is_super_admin', true)->json('data.permissions');

    expect($keys)->toContain('roles.manage', 'content.review', 'audit.view');
});

it('ends the session of a user deactivated mid-session', function () {
    $user = staffUser();
    $this->actingAs($user);
    $user->forceFill(['is_active' => false])->save();

    assertErrorCode($this->getJson('/api/v1/auth/me'), 'ACCOUNT_INACTIVE', 403);
});

it('treats a removed account as signed out', function () {
    $user = staffUser();
    $this->actingAs($user);
    $user->delete();
    app('auth')->forgetGuards();

    assertErrorCode($this->getJson('/api/v1/auth/me'), 'UNAUTHENTICATED', 401);
});
