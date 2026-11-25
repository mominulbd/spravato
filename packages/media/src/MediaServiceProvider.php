<?php

declare(strict_types=1);

namespace Uptms\Media;

use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Uptms\Media\Models\Media;
use Uptms\Media\Models\MediaFolder;
use Uptms\Media\Policies\MediaPolicy;
use Uptms\Media\Services\ImageProcessor;
use Uptms\Media\Services\MediaPresenter;
use Uptms\Media\Services\MediaStorage;
use Uptms\Media\Services\MediaUsageRecorder;

final class MediaServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        ImageProcessor::class => ImageProcessor::class,
        MediaStorage::class => MediaStorage::class,
        MediaPresenter::class => MediaPresenter::class,
        MediaUsageRecorder::class => MediaUsageRecorder::class,
    ];

    protected array $morphMap = [
        'media' => Media::class,
        'media_folder' => MediaFolder::class,
    ];

    protected array $policies = [
        Media::class => MediaPolicy::class,
    ];

    protected function bootModule(): void
    {
        RateLimiter::for('media-uploads', fn (Request $request) => Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('media:purge-trash')->dailyAt('03:15')->onOneServer();
        });
    }
}
