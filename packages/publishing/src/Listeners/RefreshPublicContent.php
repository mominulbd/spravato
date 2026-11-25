<?php

declare(strict_types=1);

namespace Uptms\Publishing\Listeners;

use Uptms\Publishing\Events\ContentPublished;
use Uptms\Publishing\Jobs\RevalidateFrontend;
use Uptms\Publishing\Services\PublicCache;

final readonly class RefreshPublicContent
{
    public function __construct(private PublicCache $cache) {}

    public function handle(ContentPublished $event): void
    {
        $this->cache->flush();

        RevalidateFrontend::dispatch($event->tags);
    }
}
