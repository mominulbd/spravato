<?php

declare(strict_types=1);

namespace Uptms\Users\Actions;

use Illuminate\Support\Facades\DB;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;
use Uptms\Users\Services\StaffGuard;

/**
 * Create an account without a password and email the invitation. The person
 * chooses a password, then sets up 2FA, when they accept.
 */
final class InviteUserAction
{
    public function __construct(
        private readonly StaffGuard $guard,
        private readonly SendInvitationAction $sendInvitation,
    ) {}

    /**
     * @param  array{name: string, email: string, role_id: int}  $data
     */
    public function execute(array $data, User $actor): User
    {
        $role = Role::query()->findOrFail($data['role_id']);
        $this->guard->assertMayAssign($actor, $role);

        $user = DB::transaction(function () use ($data, $role): User {
            $user = new User(['name' => $data['name'], 'email' => mb_strtolower($data['email'])]);
            $user->forceFill(['role_id' => $role->id, 'is_active' => true, 'password' => null])->save();

            return $user;
        });

        $this->sendInvitation->execute($user, $actor);

        return $user;
    }
}
