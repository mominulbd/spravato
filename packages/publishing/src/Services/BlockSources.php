<?php

declare(strict_types=1);

namespace Uptms\Publishing\Services;

use Closure;
use Uptms\Publishing\Contracts\BlockSource;

/**
 * Registry of BlockSource implementations, filled by each content module's
 * service provider. A missing source resolves to null so a disabled module
 * hides its blocks instead of breaking the page.
 */
final class BlockSources
{
    /** @var array<string, class-string<BlockSource>|Closure(): BlockSource> */
    private array $sources = [];

    /**
     * @param  class-string<BlockSource>|Closure(): BlockSource  $source
     */
    public function register(string $key, string|Closure $source): void
    {
        $this->sources[$key] = $source;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function resolve(string $key, array $params = []): mixed
    {
        return isset($this->sources[$key]) ? $this->make($key)->resolve($params) : null;
    }

    /** @return list<array{value: string, label: string}> */
    public function options(string $key): array
    {
        return isset($this->sources[$key]) ? $this->make($key)->options() : [];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->sources);
    }

    private function make(string $key): BlockSource
    {
        $source = $this->sources[$key];

        return $source instanceof Closure ? $source() : app($source);
    }
}
