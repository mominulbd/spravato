<?php

declare(strict_types=1);

namespace Uptms\Users\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\Users\Models\User;
use Uptms\Users\Services\StaffGuard;

/**
 * Soft-delete an account (history and audit rows keep their author) and end
 * its sessions. Pending invitations die with it.
 */
final class RemoveUserAction
{
    public function __construct(private readonly StaffGuard $guard) {}

    public function execute(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'You cannot remove your own account.');
        }

        $this->guard->assertMayManage($actor, $user);

        DB::transaction(function () use ($user): void {
            $user->invitations()->whereNull('accepted_at')->update(['expires_at' => now()]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }
}
