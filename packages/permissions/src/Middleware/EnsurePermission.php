<?php

declare(strict_types=1);

namespace Uptms\Permissions\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Uptms\Permissions\Contracts\HasPermissions;

/**
 * Route-level permission gate, the first of four (AGENTS.md).
 *
 * `permission:users.manage` requires that key; `permission:a|b` requires any.
 * The refusal names the permission so the UI can say what is missing.
 */
final class EnsurePermission
{
    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof HasPermissions) {
            throw new AuthenticationException;
        }

        $candidates = explode('|', $permissions);

        foreach ($candidates as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        throw new AuthorizationException(
            count($candidates) === 1
                ? "This action requires the {$candidates[0]} permission."
                : 'This action requires one of: '.implode(', ', $candidates).'.',
        );
    }
}
