<?php

declare(strict_types=1);

use Uptms\AuditLog\Models\AuditLog;
use Uptms\Settings\Services\Settings;
use Uptms\Settings\Services\SettingsRegistry;

/*
 * The site-wide two-factor switch (Settings → Security). phpunit.xml turns
 * it on so the rest of the suite covers enforcement; these tests turn it off.
 */

function twoFactorLogin(bool $on): void
{
    app(Settings::class)->update('security', ['two_factor_login' => $on]);
}

it('is off on a fresh install', function () {
    config(['auth.two_factor_login' => false]);

    expect(app(SettingsRegistry::class)->definitions()['security']['two_factor_login']['default'])->toBeFalse()
        ->and(app(Settings::class)->get('security.two_factor_login'))->toBeFalse();
});

it('signs in with only a password while the switch is off, even with 2FA set up', function () {
    twoFactorLogin(false);
    $user = staffUser('staff', ['email' => 'joy@uptms.test']);

    $this->postJson('/api/v1/auth/login', ['email' => 'joy@uptms.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.two_factor', false)
        ->assertJsonPath('data.user.two_factor.available', false)
        ->assertJsonPath('data.user.two_factor.enabled', true)
        ->assertJsonPath('data.user.two_factor.required', false);

    $this->assertAuthenticatedAs($user);
    expect(AuditLog::query()->where('action', 'auth.login')->value('new_values'))->toBe(['method' => 'password']);
});

it('asks for a code again once the switch is back on', function () {
    twoFactorLogin(true);
    staffUser('staff', ['email' => 'joy@uptms.test']);

    $this->postJson('/api/v1/auth/login', ['email' => 'joy@uptms.test', 'password' => 'password'])
        ->assertOk()->assertJsonPath('data.two_factor', true);
    $this->assertGuest();
});

it('does not force set-up while the switch is off', function () {
    twoFactorLogin(false);
    $user = staffUser('admin', twoFactor: false);

    asAdmin($user)->getJson('/api/v1/admin/settings')->assertOk();
    asAdmin($user)->getJson('/api/v1/auth/two-factor')->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.required', false);

    twoFactorLogin(true);
    assertErrorCode(asAdmin($user)->getJson('/api/v1/admin/settings'), 'TWO_FACTOR_SETUP_REQUIRED', 403);
});

it('lets a settings manager flip the switch, audited', function () {
    asAdmin(staffUser('admin'))->putJson('/api/v1/admin/settings/security', ['two_factor_login' => false])
        ->assertOk()->assertJsonPath('data.security.two_factor_login', false);

    expect(app(Settings::class)->get('security.two_factor_login'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'settings.updated')->where('auditable_label', 'security')->exists())->toBeTrue();
});

it('refuses the switch to staff without settings.manage, and a non-boolean value', function () {
    assertErrorCode(asAdmin(staffUser('staff'))->putJson('/api/v1/admin/settings/security', ['two_factor_login' => false]), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin(staffUser('admin'))->putJson('/api/v1/admin/settings/security', ['two_factor_login' => 'maybe']), 'VALIDATION_FAILED', 422);

    expect(app(Settings::class)->get('security.two_factor_login'))->toBeTrue();
});

it('never shows the switch on the public settings endpoint', function () {
    $this->getJson('/api/v1/public/settings')->assertOk()->assertJsonMissingPath('data.security');
});
