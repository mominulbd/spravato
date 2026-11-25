<?php

declare(strict_types=1);

namespace Uptms\Pages\Services;

use Uptms\Pages\Models\Page;
use Uptms\Pages\Models\PageBlock;

/** The snapshot format stored in revisions, and its fingerprint. */
final class PageContent
{
    /**
     * @return array{title: string, blocks: list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>}
     */
    public function snapshot(Page $page): array
    {
        $page->loadMissing('blocks');

        return [
            'title' => $page->title,
            'blocks' => array_values($page->blocks->map(fn (PageBlock $block): array => [
                'id' => $block->id,
                'type' => $block->type,
                'data' => $block->data,
                'is_visible' => $block->is_visible,
            ])->all()),
        ];
    }

    /**
     * Ids are left out so a restore (new ids, same content) counts as unchanged.
     *
     * @param  list<array{type: string, data: array<string, mixed>, is_visible: bool}>  $blocks
     */
    public function hash(array $blocks): string
    {
        return sha1((string) json_encode(array_map(fn (array $block): array => [$block['type'], $this->sorted($block['data']), $block['is_visible']], $blocks)));
    }

    /**
     * MySQL JSON columns re-order object keys, so compare with keys sorted.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function sorted(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => is_array($item) ? $this->sorted($item) : $item, $value);
    }
}
