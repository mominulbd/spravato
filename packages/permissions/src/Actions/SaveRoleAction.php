<?php

declare(strict_types=1);

namespace Uptms\Permissions\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Permissions\Models\Permission;
use Uptms\Permissions\Models\Role;

/**
 * Create or update a role and its grants. The Super Admin role always holds
 * everything, so it cannot be edited; permission changes are audited as one
 * `permission.changed` row with the before/after key lists.
 */
final class SaveRoleAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array{name: string, slug?: string, description?: string|null, requires_two_factor: bool, permissions: list<string>}  $data
     */
    public function execute(?Role $role, array $data): Role
    {
        if ($role?->isSuperAdmin() === true) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'The Super Admin role always holds every permission and cannot be edited.');
        }

        return DB::transaction(function () use ($role, $data): Role {
            $role ??= new Role(['slug' => $data['slug'] ?? '']);
            $role->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'requires_two_factor' => $data['requires_two_factor'],
            ])->save();

            $this->syncPermissions($role, $data['permissions']);

            return $role->load('permissions')->loadCount('users');
        });
    }

    /** @param  list<string>  $keys */
    private function syncPermissions(Role $role, array $keys): void
    {
        $before = $role->permissions()->pluck('key')->sort()->values()->all();
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id')->all());
        sort($keys);

        if ($before !== $keys) {
            $this->audit->record('permission.changed', $role, ['permissions' => $before], ['permissions' => $keys]);
        }
    }
}
