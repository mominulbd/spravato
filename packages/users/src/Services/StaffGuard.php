<?php

declare(strict_types=1);

namespace Uptms\Users\Services;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;

/**
 * Privilege-escalation rules shared by every user write (docs/ARCHITECTURE.md §5):
 * only a Super Admin touches a Super Admin or hands out that role, and nobody
 * hands out a role holding permissions they lack themselves.
 */
final class StaffGuard
{
    public function assertMayManage(User $actor, User $target): void
    {
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'Only a Super Admin can change a Super Admin.');
        }
    }

    public function assertMayAssign(User $actor, Role $role): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($role->isSuperAdmin()) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'Only a Super Admin can make someone a Super Admin.', [
                'role_id' => ['Only a Super Admin can assign this role.'],
            ]);
        }

        $role->loadMissing('permissions');
        $missing = array_values(array_diff($role->permissions->pluck('key')->all(), $actor->permissionKeys()));

        if ($missing !== []) {
            throw new GenericDomainException(ErrorCode::Forbidden, "You cannot assign the {$role->name} role: it holds permissions you do not have.", [
                'role_id' => ["The {$role->name} role grants ".implode(', ', $missing).'.'],
            ]);
        }
    }
}
