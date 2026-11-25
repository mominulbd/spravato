<?php

declare(strict_types=1);

namespace Uptms\Pages\Services;

use Uptms\Pages\Models\Page;
use Uptms\Publishing\Domain\Enums\PublishStatus;

/**
 * What other modules may ask about pages (menus link to them by id, so a
 * renamed address follows automatically, Figma 17).
 */
final class PageDirectory
{
    /**
     * @param  list<int>  $ids
     * @return array<int, array{id: int, title: string, path: string, published: bool}>
     */
    public function find(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Page::query()->whereIn('id', $ids)->get(['id', 'title', 'path', 'status'])
            ->mapWithKeys(fn (Page $page): array => [$page->id => [
                'id' => $page->id,
                'title' => $page->title,
                'path' => $page->path,
                'published' => $page->status === PublishStatus::Published,
            ]])->all();
    }

    public function exists(int $id): bool
    {
        return Page::query()->whereKey($id)->exists();
    }
}
