<?php

declare(strict_types=1);

namespace Uptms\Auth;

use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Uptms\Auth\Http\Controllers\SessionController;
use Uptms\Auth\Middleware\RequireTwoFactor;
use Uptms\Auth\Middleware\TouchLastSeen;
use Uptms\Auth\Services\Totp;
use Uptms\Auth\Services\TwoFactorGate;
use Uptms\Auth\Services\TwoFactorService;

final class AuthServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        Totp::class => Totp::class,
        TwoFactorService::class => TwoFactorService::class,
        TwoFactorGate::class => TwoFactorGate::class,
    ];

    protected function bootModule(): void
    {
        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('admin', RequireTwoFactor::class);
        $router->pushMiddlewareToGroup('admin', TouchLastSeen::class);

        // Five codes a minute per account (signed in, or half-way through
        // sign-in); with ±1 step that leaves an attacker ~1 in 66,000 a minute.
        RateLimiter::for('two-factor', function (Request $request): Limit {
            $pending = $request->hasSession() ? $request->session()->get(SessionController::PENDING) : null;
            $userId = $request->user()?->getAuthIdentifier() ?? (is_array($pending) ? $pending['user_id'] ?? null : null);

            return Limit::perMinute(5)->by(is_scalar($userId) ? 'user:'.$userId : 'ip:'.$request->ip());
        });
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));

        // Reset links open the admin SPA, not a Blade page.
        ResetPassword::createUrlUsing(fn (object $user, string $token): string => rtrim((string) config('app.url'), '/')
            .'/admin/reset-password/'.$token.'?email='.rawurlencode((string) data_get($user, 'email')));
    }
}
