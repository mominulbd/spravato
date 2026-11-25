<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Http\SiteClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // N+1 queries and silently dropped attributes fail loudly outside
        // production (docs/ARCHITECTURE.md §10).
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        $this->configureRateLimiting();

        // The Next.js site fronts Laravel on the same domain
        // (docs/DEVELOPMENT-PLAN.md §1): `next dev` rewrites locally, a reverse
        // proxy in production. Trusting it makes URLs and redirects use the
        // public origin instead of Laravel's own address.
        TrustProxies::at(config('app.trusted_proxies'));

        // `composer dev` also runs the Next.js site (site/), which fronts
        // Laravel on one origin: open http://localhost:3000.
        DevCommands::register('npm --prefix site run dev', 'site');
    }

    /** Named limiters (docs/API.md §3). */
    private function configureRateLimiting(): void
    {
        $byUser = fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(240)->by($byUser($request)));

        // Read traffic from the Next.js server is cached there; this caps abuse.
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(300)->by((string) $request->ip()));

        // Public form posts (consultation requests), keyed by the visitor's
        // address the site forwards, not the Next.js server's.
        RateLimiter::for('public-forms', fn (Request $request) => Limit::perMinute(5)->by(SiteClient::ip($request)));
    }
}
