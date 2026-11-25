<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('emails a reset link that opens the admin SPA', function () {
    Notification::fake();
    $user = staffUser('staff', ['email' => 'joy@uptms.test']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'joy@uptms.test'])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, '/admin/reset-password/') && str_contains($url, 'email=joy%40uptms.test');
    });
});

it('answers the same for unknown, invited and deactivated emails', function () {
    Notification::fake();
    staffUser('staff', ['email' => 'dana@uptms.test', 'password' => null]);
    staffUser('staff', ['email' => 'gone@uptms.test', 'is_active' => false]);

    foreach (['nobody@uptms.test', 'dana@uptms.test', 'gone@uptms.test'] as $email) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])
            ->assertOk()->assertJsonPath('message', 'If that email belongs to an account, a reset link is on its way.');
    }

    Notification::assertNothingSent();
});

it('resets the password with a valid token and rejects a bad one', function () {
    $user = staffUser('staff', ['email' => 'joy@uptms.test']);
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'nope', 'email' => 'joy@uptms.test', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'joy@uptms.test', 'password' => 'short', 'password_confirmation' => 'short',
    ])->assertStatus(422)->assertJsonValidationErrors(['password']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token, 'email' => 'joy@uptms.test', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1',
    ])->assertOk();

    expect(Hash::check('brand-new-pass-1', (string) $user->refresh()->password))->toBeTrue();
});
