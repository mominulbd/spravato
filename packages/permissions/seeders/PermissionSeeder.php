<?php

declare(strict_types=1);

namespace Uptms\Permissions\Seeders;

use Illuminate\Database\Seeder;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Permissions\Models\Permission;
use Uptms\Permissions\Models\Role;
use Uptms\Permissions\Services\PermissionRegistry;

/**
 * Seeds the permission registry and the four built-in roles. Idempotent:
 * new permissions are added and labels refreshed; a role that already exists
 * keeps the grants an admin gave it, except the Super Admin, which always
 * holds everything. Every built-in role starts with 2FA required, because
 * each can see patient contact details.
 */
class PermissionSeeder extends Seeder
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly AuditRecorder $audit,
    ) {}

    public function run(): void
    {
        $this->audit->withoutAuditing(function (): void {
            foreach ($this->registry->keys() as $key) {
                [$module, $action] = explode('.', $key, 2);
                [$label, $description] = $this->registry->describe($key);

                Permission::query()->updateOrCreate(
                    ['key' => $key],
                    ['module' => $module, 'action' => $action, 'label' => $label, 'description' => $description],
                );
            }

            $permissionIds = Permission::query()->pluck('id', 'key');

            foreach ($this->registry->roles() as $slug => $definition) {
                $role = Role::query()->firstOrNew(['slug' => $slug]);
                $isNew = ! $role->exists;

                $role->fill(['name' => $definition['name'], 'description' => $definition['description']]);
                $role->is_system = true;
                $role->save();

                if ($isNew || $slug === PermissionRegistry::SUPER_ADMIN) {
                    $keys = $this->registry->expand($definition['grants']);
                    $role->permissions()->sync($permissionIds->only($keys)->values()->all());
                }
            }
        });
    }
}
