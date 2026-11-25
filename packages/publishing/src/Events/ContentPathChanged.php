<?php

declare(strict_types=1);

namespace Uptms\Publishing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A live page, post or condition moved to a new address. The redirects
 * module answers with a 301 from the old address, so links and search
 * results keep working.
 */
final readonly class ContentPathChanged
{
    use Dispatchable;

    public function __construct(public string $from, public string $to) {}
}
