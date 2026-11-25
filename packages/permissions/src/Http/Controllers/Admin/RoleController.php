<?php

declare(strict_types=1);

namespace Uptms\Permissions\Http\Controllers\Admin;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Permissions\Actions\SaveRoleAction;
use Uptms\Permissions\Http\Requests\SaveRoleRequest;
use Uptms\Permissions\Http\Resources\RoleResource;
use Uptms\Permissions\Models\Role;
use Uptms\Permissions\Services\PermissionRegistry;

class RoleController
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->withCount('users')->orderBy('id')->get();

        return ApiResponse::success(RoleResource::collection($roles), 'Roles retrieved.');
    }

    /** The matrix rows of Figma screen 26, grouped. */
    public function permissions(PermissionRegistry $registry): JsonResponse
    {
        return ApiResponse::success(['groups' => $registry->groups()], 'Permission registry retrieved.');
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(new RoleResource($role->load('permissions')->loadCount('users')), 'Role retrieved.');
    }

    public function store(SaveRoleRequest $request, SaveRoleAction $action): JsonResponse
    {
        /** @phpstan-ignore argument.type (validated shape) */
        $role = $action->execute(null, $request->validated());

        return ApiResponse::created(new RoleResource($role), 'Role created.');
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRoleAction $action): JsonResponse
    {
        /** @phpstan-ignore argument.type (validated shape) */
        $role = $action->execute($role, $request->validated());

        return ApiResponse::success(new RoleResource($role), 'Role updated.');
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'Built-in roles cannot be deleted; edit their permissions instead.');
        }

        if ($role->users()->exists()) {
            throw new GenericDomainException(ErrorCode::Conflict, 'Move everyone off this role before deleting it.');
        }

        $role->delete();

        return ApiResponse::success(null, 'Role deleted.');
    }

    /**
     * The "Require 2FA" action on Figma screen 25: every role that can see
     * patient contact details must confirm TOTP.
     */
    public function requireTwoFactor(AuditRecorder $audit): JsonResponse
    {
        $roles = DB::transaction(function () {
            $roles = Role::query()
                ->where('requires_two_factor', false)
                ->where(fn ($query) => $query
                    ->where('slug', PermissionRegistry::SUPER_ADMIN)
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->where('key', 'consultations.view')))
                ->get();

            foreach ($roles as $role) {
                $role->update(['requires_two_factor' => true]);
            }

            return $roles;
        });

        return ApiResponse::success(
            ['updated' => $roles->pluck('slug')->values()->all()],
            $roles->isEmpty() ? 'Every role that sees requests already requires 2FA.' : 'Two-factor authentication is now required.',
        );
    }
}
