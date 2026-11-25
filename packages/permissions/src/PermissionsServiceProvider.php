<?php

declare(strict_types=1);

namespace Uptms\Permissions;

use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Uptms\Permissions\Contracts\HasPermissions;
use Uptms\Permissions\Middleware\EnsurePermission;
use Uptms\Permissions\Models\Permission;
use Uptms\Permissions\Models\Role;
use Uptms\Permissions\Services\PermissionRegistry;

final class PermissionsServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        PermissionRegistry::class => PermissionRegistry::class,
    ];

    protected array $morphMap = [
        'role' => Role::class,
        'permission' => Permission::class,
    ];

    protected function bootModule(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('permission', EnsurePermission::class);

        // Every admin route needs admin.access on top of a session.
        $router->pushMiddlewareToGroup('admin', 'permission:admin.access');

        // A Super Admin passes every check (docs/ARCHITECTURE.md §5).
        // `$user->can('users.manage')` resolves through the registry, so Form
        // Requests and policies share one source of truth. Abilities that are
        // not registry keys (policy methods) fall through to the policy.
        $registry = $this->app->make(PermissionRegistry::class);

        Gate::before(function (mixed $user, string $ability) use ($registry): ?bool {
            if (! $user instanceof HasPermissions) {
                return null;
            }

            if ($registry->exists($ability)) {
                return $user->hasPermission($ability);
            }

            return $user->isSuperAdmin() ? true : null;
        });
    }
}
