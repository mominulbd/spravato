<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Closure;
use Uptms\Seo\Contracts\SeoSource;
use Uptms\Seo\Domain\SeoSubject;

/** Registry of SeoSource implementations, filled by content modules' providers. */
final class SeoSources
{
    /** @var array<string, class-string<SeoSource>|Closure(): SeoSource> */
    private array $sources = [];

    /** @var array<string, SeoSource> */
    private array $resolved = [];

    /** @var array{0: int, 1: list<SeoSubject>}|null one request's site index */
    private ?array $memo = null;

    /** @param class-string<SeoSource>|Closure(): SeoSource $source */
    public function register(string $type, string|Closure $source): void
    {
        $this->sources[$type] = $source;
        unset($this->resolved[$type]);
    }

    public function has(string $type): bool
    {
        return isset($this->sources[$type]);
    }

    public function get(string $type): ?SeoSource
    {
        if (! isset($this->sources[$type])) {
            return null;
        }

        $source = $this->sources[$type];

        return $this->resolved[$type] ??= $source instanceof Closure ? $source() : app($source);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->sources);
    }

    /**
     * Every published subject across modules. Built once per request (the
     * SEO tabs ask for it several times while scoring and suggesting links).
     *
     * @return list<SeoSubject>
     */
    public function allPublished(): array
    {
        $owner = spl_object_id(app('request'));

        if ($this->memo !== null && $this->memo[0] === $owner) {
            return $this->memo[1];
        }

        $all = [];

        foreach ($this->types() as $type) {
            foreach ($this->get($type)?->published() ?? [] as $subject) {
                $all[] = $subject;
            }
        }

        $this->memo = [$owner, $all];

        return $all;
    }
}
