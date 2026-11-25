<?php

declare(strict_types=1);

namespace Uptms\Seo\Domain;

use Uptms\Seo\Domain\Enums\IssueSeverity;

/**
 * One row of Figma 19's issue list: a kind of problem and every page (or
 * image, or address) it affects. A check with nothing to report returns a
 * passed issue, so the list also shows what was checked.
 */
final readonly class SeoIssue
{
    /**
     * @param  list<array{key: string, label: string, path: string|null, admin_path: string|null}>  $items  what is affected; `key` is stable between audits ("page:4", "image:12", "/old-path")
     * @param  array{label: string, to: string}|null  $action  where to fix it; null sends staff to the items
     */
    public function __construct(
        public string $key,
        public IssueSeverity $severity,
        public string $title,
        public string $detail,
        public array $items = [],
        public ?array $action = null,
    ) {}

    /** Shorthand for a check that found nothing. */
    public static function passed(string $key, string $title, string $detail): self
    {
        return new self($key, IssueSeverity::Passed, $title, $detail);
    }

    /**
     * "OCD guide, TMS therapy, Contact", or the first few and a count.
     *
     * @param  list<array{key: string, label: string, path: string|null, admin_path: string|null}>  $items
     */
    public static function names(array $items, int $show = 3): string
    {
        $labels = array_map(fn (array $item): string => $item['label'], array_slice($items, 0, $show));
        $more = count($items) - count($labels);

        return implode(', ', $labels).($more > 0 ? " and {$more} more" : '');
    }

    /**
     * @return array{key: string, severity: string, title: string, detail: string, count: int, items: list<array{key: string, label: string, path: string|null, admin_path: string|null}>, action: array{label: string, to: string}|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'detail' => $this->detail,
            'count' => count($this->items),
            'items' => $this->items,
            'action' => $this->action,
        ];
    }
}
