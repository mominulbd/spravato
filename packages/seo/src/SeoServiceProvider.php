<?php

declare(strict_types=1);

namespace Uptms\Seo;

use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Uptms\Seo\Models\SeoMeta;
use Uptms\Seo\Services\SeoAnalyzer;
use Uptms\Seo\Services\SeoAuditChecks;
use Uptms\Seo\Services\SeoSettings;
use Uptms\Seo\Services\SeoSources;

final class SeoServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        SeoSources::class => SeoSources::class,
        SeoSettings::class => SeoSettings::class,
        SeoAnalyzer::class => SeoAnalyzer::class,
        SeoAuditChecks::class => SeoAuditChecks::class,
    ];

    protected array $morphMap = [
        'seo_meta' => SeoMeta::class,
    ];

    protected function bootModule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('seo:notify')->everyTenMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('seo:audit')->dailyAt('06:00')->timezone((string) config('seo.timezone'))->withoutOverlapping()->onOneServer();
        });
    }
}
