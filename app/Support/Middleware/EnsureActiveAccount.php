<?php

declare(strict_types=1);

namespace App\Support\Middleware;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account keeps its session but can do nothing with it: the
 * session is ended and the request refused with ACCOUNT_INACTIVE.
 */
final class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Model && $user->getAttribute('is_active') === false) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            throw new GenericDomainException(ErrorCode::AccountInactive, 'This account has been deactivated.');
        }

        return $next($request);
    }
}
