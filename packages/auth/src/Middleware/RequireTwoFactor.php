<?php

declare(strict_types=1);

namespace Uptms\Auth\Middleware;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Uptms\Auth\Services\TwoFactorGate;
use Uptms\Users\Models\User;

/**
 * On every admin route: a user whose role requires 2FA but who has not set
 * it up can do nothing until they do, while the site-wide switch is on.
 * The SPA answers
 * TWO_FACTOR_SETUP_REQUIRED by opening the set-up screen.
 */
final class RequireTwoFactor
{
    public function __construct(private readonly TwoFactorGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $this->gate->requires($user) && ! $user->hasTwoFactorEnabled()) {
            throw new GenericDomainException(ErrorCode::TwoFactorSetupRequired, 'Set up two-factor authentication to continue.');
        }

        return $next($request);
    }
}
