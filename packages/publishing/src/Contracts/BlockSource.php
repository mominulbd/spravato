<?php

declare(strict_types=1);

namespace Uptms\Publishing\Contracts;

/**
 * Supplies the data a page block shows from another module, e.g. the FAQ
 * block reads published FAQs from collections. Pages never query another
 * module's tables; they ask the source registered under the block's key.
 */
interface BlockSource
{
    /** Registry key, e.g. `faqs`. */
    public function key(): string;

    /**
     * What the website receives for this block.
     *
     * @param  array<string, mixed>  $params  the block's settings (e.g. `group`)
     */
    public function resolve(array $params): mixed;

    /**
     * Choices for the admin's source picker (e.g. FAQ groups).
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array;
}
