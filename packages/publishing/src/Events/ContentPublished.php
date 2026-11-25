<?php

declare(strict_types=1);

namespace Uptms\Publishing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something the website shows changed. Listeners clear the public read
 * cache and ask the website to revalidate `tags` (site/ `cacheTag`s).
 */
final class ContentPublished
{
    use Dispatchable;

    /**
     * @param  list<string>  $tags  e.g. ['pages', 'page:home']
     */
    public function __construct(public readonly array $tags) {}
}
