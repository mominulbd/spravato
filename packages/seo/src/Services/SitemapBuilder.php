<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Uptms\Media\Services\MediaPresenter;
use Uptms\Seo\Domain\SeoSubject;
use Uptms\Seo\Models\SeoMeta;

/**
 * The XML sitemap's entries (Figma 22): every published page, condition and
 * post that may be indexed, unless its group is switched off or its SEO tab
 * leaves it out. Images ride along with the page they appear on.
 */
final readonly class SitemapBuilder
{
    /** Source type → settings key and the label of Figma 22. */
    public const array GROUPS = [
        'page' => ['setting' => 'include_pages', 'label' => 'Pages'],
    ];

    public function __construct(private SeoSources $sources, private SeoSettings $settings, private MediaPresenter $media) {}

    /**
     * @return list<array{path: string, type: string, lastmod: string|null, priority: float, images: list<array{url: string, alt: string}>}>
     */
    public function entries(): array
    {
        $subjects = array_values(array_filter($this->sources->allPublished(), fn (SeoSubject $s): bool => $this->groupOn($s->type)));
        $excluded = $this->excluded($subjects);
        $withImages = (bool) $this->settings->get('include_images');
        $withDates = (bool) $this->settings->get('include_lastmod');
        $images = $withImages ? $this->media->presentMany(array_values(array_unique(array_merge(...array_map(fn (SeoSubject $s): array => array_values(array_filter(array_column($s->images, 'id'), 'is_int')), $subjects))))) : [];
        $entries = [];

        foreach ($subjects as $subject) {
            if (isset($excluded[$subject->key()])) {
                continue;
            }

            $entries[] = [
                'path' => $subject->path,
                'type' => $subject->type,
                'lastmod' => $withDates ? ($subject->updatedAt ?? $subject->publishedAt)?->toIso8601String() : null,
                'priority' => $subject->sitemapPriority,
                'images' => array_values(array_filter(array_map(function (array $image) use ($images): ?array {
                    $found = is_int($image['id']) ? ($images[$image['id']] ?? null) : null;

                    return $found === null ? null : ['url' => $found['url'], 'alt' => $found['alt']];
                }, $subject->images))),
            ];
        }

        usort($entries, fn (array $a, array $b): int => [$b['priority'], $a['path']] <=> [$a['priority'], $b['path']]);

        return $entries;
    }

    /**
     * Figma 22's group list with URL counts.
     *
     * @return list<array{key: string, label: string, setting: string, enabled: bool, count: int}>
     */
    public function groups(): array
    {
        $all = $this->sources->allPublished();
        $excluded = $this->excluded($all);
        $groups = [];

        foreach (self::GROUPS as $type => $group) {
            $groups[] = [
                'key' => $type,
                'label' => $group['label'],
                'setting' => $group['setting'],
                'enabled' => $this->groupOn($type),
                'count' => count(array_filter($all, fn (SeoSubject $s): bool => $s->type === $type && ! isset($excluded[$s->key()]))),
            ];
        }

        $imageIds = array_unique(array_merge(...array_map(fn (SeoSubject $s): array => array_values(array_filter(array_column($s->images, 'id'), 'is_int')), $all)));
        $groups[] = ['key' => 'images', 'label' => 'Images', 'setting' => 'include_images', 'enabled' => (bool) $this->settings->get('include_images'), 'count' => count($imageIds)];

        return $groups;
    }

    private function groupOn(string $type): bool
    {
        $setting = self::GROUPS[$type]['setting'] ?? null;

        return $setting === null || (bool) $this->settings->get($setting);
    }

    /**
     * Subjects whose SEO tab hides them from search or the sitemap.
     *
     * @param  list<SeoSubject>  $subjects
     * @return array<string, true>
     */
    private function excluded(array $subjects): array
    {
        $types = array_values(array_unique(array_map(fn (SeoSubject $s): string => $s->type, $subjects)));

        return SeoMeta::query()->whereIn('seoable_type', $types)
            ->where(fn ($q) => $q->where('robots_index', false)->orWhere('in_sitemap', false))
            ->get(['seoable_type', 'seoable_id'])
            ->mapWithKeys(fn (SeoMeta $m): array => [$m->seoable_type.':'.$m->seoable_id => true])
            ->all();
    }
}
