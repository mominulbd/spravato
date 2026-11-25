<?php

declare(strict_types=1);

namespace Uptms\Permissions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Uptms\Permissions\Models\Permission;
use Uptms\Permissions\Models\Role;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_super_admin' => $this->isSuperAdmin(),
            'requires_two_factor' => $this->requires_two_factor,
            'permissions' => $this->whenLoaded('permissions', fn (): array => $this->permissions
                ->map(fn (Permission $permission): string => $permission->key)->sort()->values()->all()),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
