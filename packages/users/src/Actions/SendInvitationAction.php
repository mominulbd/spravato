<?php

declare(strict_types=1);

namespace Uptms\Users\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Users\Domain\Enums\UserStatus;
use Uptms\Users\Models\User;
use Uptms\Users\Models\UserInvitation;
use Uptms\Users\Notifications\InvitationNotification;

/**
 * Issue a fresh invitation link. Earlier unaccepted links for the user stop
 * working, so a resend never leaves two live links.
 */
final class SendInvitationAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(User $user, User $actor): UserInvitation
    {
        if ($user->status() !== UserStatus::Invited) {
            throw new GenericDomainException(ErrorCode::Conflict, 'This person has already accepted their invitation.');
        }

        $token = Str::random(64);
        $expiresAt = now()->addDays((int) config('users.invitation_days', 7));

        $invitation = DB::transaction(function () use ($user, $actor, $token, $expiresAt): UserInvitation {
            $user->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->update(['expires_at' => now()]);

            $invitation = $user->invitations()->create([
                'invited_by' => $actor->id,
                'token_hash' => UserInvitation::hashToken($token),
                'expires_at' => $expiresAt,
            ]);

            $this->audit->record('user.invited', $user, null, ['expires_at' => $expiresAt->toIso8601String()]);

            return $invitation;
        });

        $user->notify(new InvitationNotification($token, $actor->name, $expiresAt));

        return $invitation;
    }
}
