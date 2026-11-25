<?php

declare(strict_types=1);

namespace Uptms\Publishing\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache for `/api/v1/public/*` reads. The website is small, so any publish
 * invalidates every entry by bumping one version number; that works on
 * every cache store (no tag support needed) and can never serve a stale mix.
 */
class PublicCache
{
    private const string VERSION_KEY = 'public-content:version';

    private const int TTL_SECONDS = 3600;

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember('public:v'.$this->version().':'.$key, self::TTL_SECONDS, $callback);
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    private function version(): int
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return is_numeric($version) ? (int) $version : 1;
    }
}
