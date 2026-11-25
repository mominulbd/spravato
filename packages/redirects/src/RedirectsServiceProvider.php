<?php

declare(strict_types=1);

namespace Uptms\Redirects;

use App\Support\Http\SiteClient;
use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Uptms\Publishing\Events\ContentPathChanged;
use Uptms\Redirects\Listeners\RedirectMovedContent;
use Uptms\Redirects\Models\NotFoundLog;
use Uptms\Redirects\Models\Redirect;
use Uptms\Redirects\Seo\NotFoundAuditCheck;
use Uptms\Seo\Services\SeoAuditChecks;

final class RedirectsServiceProvider extends ModuleServiceProvider
{
    protected array $morphMap = [
        'redirect' => Redirect::class,
    ];

    protected array $listen = [
        ContentPathChanged::class => [RedirectMovedContent::class],
    ];

    protected function bootModule(): void
    {
        Route::model('log', NotFoundLog::class);
        $this->app->make(SeoAuditChecks::class)->register('redirects.not_found', NotFoundAuditCheck::class);

        // 404 reports: generous per visitor (a crawler can hit many), capped against floods.
        RateLimiter::for('public-forms-404', fn (Request $request) => Limit::perMinute(60)->by(SiteClient::ip($request)));
        // Redirect hits: one per followed redirect, so the same ceiling per visitor.
        RateLimiter::for('redirect-hits', fn (Request $request) => Limit::perMinute(60)->by(SiteClient::ip($request)));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('redirects:prune')->dailyAt('03:45')->onOneServer();
        });
    }
}
