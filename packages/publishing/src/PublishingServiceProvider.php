<?php

declare(strict_types=1);

namespace Uptms\Publishing;

use App\Support\Packages\ModuleServiceProvider;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Publishing\Listeners\RefreshPublicContent;
use Uptms\Publishing\Models\Revision;
use Uptms\Publishing\Services\BlockSources;
use Uptms\Publishing\Services\PublicCache;
use Uptms\Publishing\Services\Reorderer;

final class PublishingServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        BlockSources::class => BlockSources::class,
        PublicCache::class => PublicCache::class,
        Reorderer::class => Reorderer::class,
    ];

    protected array $morphMap = [
        'revision' => Revision::class,
    ];

    protected array $listen = [
        ContentPublished::class => [RefreshPublicContent::class],
    ];
}
