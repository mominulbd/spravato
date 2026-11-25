<?php

declare(strict_types=1);

namespace Uptms\Permissions\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Uptms\AuditLog\Concerns\Auditable;
use Uptms\Permissions\Services\PermissionRegistry;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property bool $requires_two_factor
 * @property-read Collection<int, Permission> $permissions
 */
class Role extends Model
{
    use Auditable;

    protected $fillable = ['slug', 'name', 'description', 'requires_two_factor'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'requires_two_factor' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === PermissionRegistry::SUPER_ADMIN;
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Staff holding this role. The user model comes from the auth config so
     * this package does not depend on the users package.
     *
     * @return HasMany<Model, $this>
     */
    public function users(): HasMany
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model');

        return $this->hasMany($model, 'role_id');
    }
}
