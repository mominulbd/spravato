<?php

declare(strict_types=1);

namespace Uptms\Pages\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Models\PageBlock;

/**
 * Admin shapes for the page list (Figma 06) and editor (Figma 07). Staff
 * names come straight from the users table so pages don't depend on the
 * users module.
 */
final class PageResource
{
    /**
     * @param  Collection<int, Page>  $pages
     * @param  array<int, int|null>  $scores  SEO scores keyed by page id
     * @return list<array<string, mixed>>
     */
    public function rows(Collection $pages, array $scores = []): array
    {
        $names = $this->names(array_values($pages->pluck('updated_by')->merge($pages->pluck('created_by'))->filter()->unique()->all()));

        return array_values($pages->map(fn (Page $page): array => [...$this->row($page, $names), 'seo_score' => $scores[$page->id] ?? null])->all());
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    public function row(Page $page, array $names = []): array
    {
        return [
            'id' => $page->id,
            'uuid' => $page->uuid,
            'title' => $page->title,
            'slug' => $page->slug,
            'path' => $page->path,
            'parent_id' => $page->parent_id,
            'depth' => $page->path === '/' ? 0 : substr_count(trim($page->path, '/'), '/'),
            'template' => $page->template->value,
            'template_label' => $page->template->label(),
            'status' => $page->status->value,
            'is_home' => $page->is_home,
            'has_unpublished_changes' => $page->hasUnpublishedChanges(),
            'published_at' => $page->published_at?->toIso8601String(),
            'scheduled_at' => $page->scheduled_at?->toIso8601String(),
            'updated_at' => ($page->content_updated_at ?? $page->updated_at)?->toIso8601String(),
            'updated_by' => $page->updated_by !== null ? ['id' => $page->updated_by, 'name' => $names[$page->updated_by] ?? null] : null,
            'created_by' => $page->created_by !== null ? ['id' => $page->created_by, 'name' => $names[$page->created_by] ?? null] : null,
            'deleted_at' => $page->deleted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Page $page): array
    {
        $page->loadMissing('blocks');
        $names = $this->names(array_values(array_filter([$page->updated_by, $page->created_by])));

        return [
            ...$this->row($page, $names),
            'lock_version' => $page->lock_version,
            'revisions_count' => $page->revisions()->count(),
            'blocks' => $page->blocks->map(fn (PageBlock $block): array => [
                'id' => $block->id,
                'type' => $block->type,
                'data' => $block->data,
                'is_visible' => $block->is_visible,
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<mixed>  $ids
     * @return array<int, string>
     */
    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> */
        return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
