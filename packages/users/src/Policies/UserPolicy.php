<?php

declare(strict_types=1);

namespace Uptms\Users\Policies;

use Uptms\Users\Models\User;

/**
 * Second gate (AGENTS.md). Super Admins pass through Gate::before; everyone
 * else needs users.manage and may not touch a Super Admin.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasPermission('users.manage') && ! $user->isSuperAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        return $this->update($actor, $user);
    }
}
