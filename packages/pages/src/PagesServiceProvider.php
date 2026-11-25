<?php

declare(strict_types=1);

namespace Uptms\Pages;

use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Uptms\Pages\Blocks\BlockTypes;
use Uptms\Pages\Blocks\BlockValidator;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Models\PageBlock;
use Uptms\Pages\Policies\PagePolicy;
use Uptms\Pages\Seo\PageSeoSource;
use Uptms\Pages\Services\PageContent;
use Uptms\Pages\Services\PageDirectory;
use Uptms\Pages\Services\PagePaths;
use Uptms\Pages\Services\PagePresenter;
use Uptms\Pages\Services\PageResource;
use Uptms\Pages\Services\PreviewTokens;
use Uptms\Seo\Services\SeoSources;

final class PagesServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        BlockTypes::class => BlockTypes::class,
        BlockValidator::class => BlockValidator::class,
        PageContent::class => PageContent::class,
        PageDirectory::class => PageDirectory::class,
        PagePaths::class => PagePaths::class,
        PagePresenter::class => PagePresenter::class,
        PageResource::class => PageResource::class,
        PreviewTokens::class => PreviewTokens::class,
    ];

    protected array $morphMap = [
        'page' => Page::class,
        'page_block' => PageBlock::class,
    ];

    protected array $policies = [
        Page::class => PagePolicy::class,
    ];

    protected function bootModule(): void
    {
        $this->app->make(SeoSources::class)->register('page', PageSeoSource::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('pages:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();
        });
    }
}
