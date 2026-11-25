<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;
use Uptms\Users\Notifications\InvitationNotification;

/** Invite someone as an Admin and return the emailed token. */
function inviteAndCaptureToken(string $email = 'dana@uptms.test'): string
{
    Notification::fake();
    $admin = staffUser('admin');

    asAdmin($admin)->postJson('/api/v1/admin/users', [
        'name' => 'Dana Osei', 'email' => $email, 'role_id' => (int) Role::query()->where('slug', 'staff')->value('id'),
    ])->assertCreated();

    $token = null;
    Notification::assertSentTo(User::query()->where('email', $email)->firstOrFail(), InvitationNotification::class, function (InvitationNotification $n, array $c, object $user) use (&$token) {
        $token = basename((string) $n->toMail($user)->actionUrl);

        return true;
    });
    app('auth')->forgetGuards();

    return (string) $token;
}

it('shows and accepts a valid invitation, then signs the person in', function () {
    $token = inviteAndCaptureToken();

    $this->getJson("/api/v1/invitations/{$token}")->assertOk()
        ->assertJsonPath('data.email', 'dana@uptms.test')
        ->assertJsonPath('data.role', 'Staff');

    $this->postJson("/api/v1/invitations/{$token}/accept", [
        'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1',
    ])->assertOk();

    $dana = User::query()->where('email', 'dana@uptms.test')->firstOrFail();
    $this->assertAuthenticatedAs($dana);
    expect($dana->status()->value)->toBe('active')
        ->and(AuditLog::query()->where('action', 'user.invitation_accepted')->exists())->toBeTrue();

    // Next stop: 2FA set-up, because Staff requires it.
    assertErrorCode($this->getJson('/api/v1/admin/users'), 'TWO_FACTOR_SETUP_REQUIRED', 403);
});

it('works once', function () {
    $token = inviteAndCaptureToken();
    $payload = ['password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'];

    $this->postJson("/api/v1/invitations/{$token}/accept", $payload)->assertOk();

    assertErrorCode($this->getJson("/api/v1/invitations/{$token}"), 'INVITATION_INVALID', 410);
});

it('rejects expired, unknown and replaced links', function () {
    $token = inviteAndCaptureToken();
    $this->travel(8)->days();

    assertErrorCode($this->getJson("/api/v1/invitations/{$token}"), 'INVITATION_INVALID', 410);
    assertErrorCode($this->getJson('/api/v1/invitations/'.str_repeat('a', 64)), 'INVITATION_INVALID', 410);
});

it('resends an invitation and kills the old link', function () {
    $old = inviteAndCaptureToken();
    $dana = User::query()->where('email', 'dana@uptms.test')->firstOrFail();

    asAdmin(staffUser('admin'))->postJson("/api/v1/admin/users/{$dana->id}/invitation")->assertOk();
    app('auth')->forgetGuards();

    assertErrorCode($this->getJson("/api/v1/invitations/{$old}"), 'INVITATION_INVALID', 410);
    Notification::assertSentToTimes($dana, InvitationNotification::class, 2);
});

it('refuses to resend to someone already active', function () {
    $joy = staffUser('staff');

    assertErrorCode(asAdmin(staffUser('admin'))->postJson("/api/v1/admin/users/{$joy->id}/invitation"), 'CONFLICT', 409);
});

it('requires a strong, confirmed password', function () {
    $token = inviteAndCaptureToken();

    $this->postJson("/api/v1/invitations/{$token}/accept", ['password' => 'short', 'password_confirmation' => 'short'])
        ->assertStatus(422)->assertJsonValidationErrors(['password']);
});
