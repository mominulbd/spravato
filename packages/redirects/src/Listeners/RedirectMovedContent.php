<?php

declare(strict_types=1);

namespace Uptms\Redirects\Listeners;

use Uptms\Publishing\Events\ContentPathChanged;
use Uptms\Redirects\Services\RedirectRules;

/** A live page, post or condition moved: send its old address to the new one. */
final readonly class RedirectMovedContent
{
    public function __construct(private RedirectRules $rules) {}

    public function handle(ContentPathChanged $event): void
    {
        $this->rules->pathChanged($event->from, $event->to);
    }
}
