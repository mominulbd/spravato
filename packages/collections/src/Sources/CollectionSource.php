<?php

declare(strict_types=1);

namespace Uptms\Collections\Sources;

use Uptms\Collections\Services\CollectionReader;
use Uptms\Collections\Types\CollectionType;
use Uptms\Publishing\Contracts\BlockSource;

/** Lets a page block show a collection (`faqs` with `group: home` …). */
final readonly class CollectionSource implements BlockSource
{
    public function __construct(
        private CollectionType $type,
        private CollectionReader $reader,
    ) {}

    public function key(): string
    {
        return $this->type->key();
    }

    public function resolve(array $params): mixed
    {
        return $this->reader->published($this->type, $params);
    }

    public function options(): array
    {
        return $this->type->sourceOptions();
    }
}
