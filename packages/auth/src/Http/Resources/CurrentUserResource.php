<?php

declare(strict_types=1);

namespace Uptms\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Uptms\Auth\Services\TwoFactorGate;
use Uptms\Permissions\Services\PermissionRegistry;
use Uptms\Users\Models\User;

/**
 * The signed-in user as the admin SPA sees it. `permissions` drive UI
 * visibility only; Laravel enforces every one of them again.
 *
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->loadMissing('role');
        $gate = app(TwoFactorGate::class);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role === null ? null : ['slug' => $this->role->slug, 'name' => $this->role->name],
            'is_super_admin' => $this->isSuperAdmin(),
            // A Super Admin holds every key, listed so the SPA needs no wildcard rule.
            'permissions' => $this->isSuperAdmin() ? app(PermissionRegistry::class)->keys() : $this->permissionKeys(),
            'two_factor' => [
                // The site-wide switch; while it is off sign-in never asks for a code.
                'available' => $gate->isOn(),
                'enabled' => $this->hasTwoFactorEnabled(),
                'required' => $gate->requires($this->resource),
            ],
            'session' => [
                'idle_timeout_minutes' => (int) config('session.lifetime'),
            ],
        ];
    }
}
