<?php

declare(strict_types=1);

namespace Uptms\Permissions\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Uptms\Permissions\Models\Role;

/**
 * One role per user (`users.role_id`). Effective permissions are the role's
 * grants; a Super Admin holds everything. Resolved once per model instance,
 * so a change takes effect on the next request.
 *
 * @property int|null $role_id
 * @property-read Role|null $role
 *
 * @mixin Model
 */
trait HasRole
{
    /** @var list<string>|null */
    private ?array $resolvedPermissions = null;

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        $this->loadMissing('role');

        return $this->role?->isSuperAdmin() ?? false;
    }

    public function hasPermission(string $key): bool
    {
        return $this->isSuperAdmin() || in_array($key, $this->permissionKeys(), true);
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        $this->loadMissing('role.permissions');

        $keys = $this->role?->permissions->pluck('key')->sort()->values()->all() ?? [];

        return $this->resolvedPermissions = array_values($keys);
    }

    public function forgetResolvedPermissions(): void
    {
        $this->resolvedPermissions = null;
        $this->unsetRelation('role');
    }
}
