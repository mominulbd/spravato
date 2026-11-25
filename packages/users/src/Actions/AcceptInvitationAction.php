<?php

declare(strict_types=1);

namespace Uptms\Users\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Users\Domain\Enums\UserStatus;
use Uptms\Users\Models\User;
use Uptms\Users\Models\UserInvitation;

/**
 * Turn a valid invitation link into a password. The token works once.
 */
final class AcceptInvitationAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** The live invitation for a token, or INVITATION_INVALID. */
    public function find(string $token): UserInvitation
    {
        $invitation = UserInvitation::query()
            ->with('user.role')
            ->where('token_hash', UserInvitation::hashToken($token))
            ->first();

        if ($invitation === null || ! $invitation->isUsable() || $invitation->user === null || $invitation->user->status() !== UserStatus::Invited) {
            throw new GenericDomainException(ErrorCode::InvitationInvalid, 'This invitation link has expired or was already used. Ask an administrator for a new one.');
        }

        return $invitation;
    }

    public function execute(string $token, string $password): User
    {
        $invitation = $this->find($token);
        $user = $invitation->invitee();

        DB::transaction(function () use ($invitation, $user, $password): void {
            $invitation->forceFill(['accepted_at' => now()])->save();
            $user->forceFill(['password' => $password, 'email_verified_at' => now()])->saveQuietly();
            $this->audit->record('user.invitation_accepted', $user, userId: $user->id);
        });

        return $user;
    }
}
