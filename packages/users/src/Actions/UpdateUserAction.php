<?php

declare(strict_types=1);

namespace Uptms\Users\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;
use Uptms\Users\Services\StaffGuard;

/**
 * Change someone's name, email, role or activation. Nobody changes their own
 * role or deactivates themselves, so the last Super Admin cannot lock the
 * clinic out. Role changes are audited as `permission.changed`.
 */
final class UpdateUserAction
{
    public function __construct(
        private readonly StaffGuard $guard,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  array{name: string, credentials?: string|null, email: string, role_id: int, is_active: bool}  $data
     */
    public function execute(User $user, array $data, User $actor): User
    {
        $this->guard->assertMayManage($actor, $user);
        $role = Role::query()->findOrFail($data['role_id']);

        if ($user->is($actor) && $role->id !== $user->role_id) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'You cannot change your own role.', [
                'role_id' => ['Ask another administrator to change your role.'],
            ]);
        }

        if ($user->is($actor) && ! $data['is_active']) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'You cannot deactivate your own account.');
        }

        if ($role->id !== $user->role_id) {
            $this->guard->assertMayAssign($actor, $role);
        }

        return DB::transaction(function () use ($user, $data, $role): User {
            $before = $user->role?->slug;
            $user->fill(['name' => $data['name'], 'email' => mb_strtolower($data['email'])]);
            if (array_key_exists('credentials', $data)) {
                $user->credentials = is_string($data['credentials']) && trim($data['credentials']) !== '' ? trim($data['credentials']) : null;
            }
            $user->forceFill(['role_id' => $role->id, 'is_active' => $data['is_active']])->save();

            if ($before !== $role->slug) {
                $this->audit->record('permission.changed', $user, ['role' => $before], ['role' => $role->slug]);
            }

            if (! $data['is_active']) {
                // A deactivated account loses every session at once.
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $user->forgetResolvedPermissions();

            return $user;
        });
    }
}
