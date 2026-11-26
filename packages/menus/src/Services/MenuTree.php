<?php

declare(strict_types=1);

namespace Uptms\Menus\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Media\Services\MediaPresenter;
use Uptms\Media\Services\MediaUsageRecorder;
use Uptms\Menus\Models\Menu;
use Uptms\Menus\Models\MenuItem;
use Uptms\Pages\Services\PageDirectory;
use Uptms\Publishing\Events\ContentPublished;

/**
 * Reads menus as trees with resolved links, and saves a whole tree at once
 * (Figma 17 "Save menu"). On the website, items pointing at an
 * unpublished page, at a document that is trashed or gone, and hidden
 * items are left out. Document items store the media id, so the address
 * is always the file's current one (replacing a PDF changes it).
 */
final readonly class MenuTree
{
    public const string SAFE_URL = '/^(\/(?!\/)|#|https:\/\/|tel:|mailto:)/';

    public function __construct(
        private PageDirectory $pages,
        private AuditRecorder $audit,
        private MediaPresenter $media,
        private MediaUsageRecorder $usages,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function admin(Menu $menu): array
    {
        return $this->build($menu, public: false);
    }

    /**
     * @return list<array{label: string, href: string, children: list<array{label: string, href: string}>}>
     */
    public function public(Menu $menu): array
    {
        /** @var list<array{label: string, href: string, children: list<array{label: string, href: string}>}> */
        return $this->build($menu, public: true);
    }

    /**
     * @param  list<array<string, mixed>>  $items  validated tree
     */
    public function save(Menu $menu, array $items): void
    {
        $before = $this->admin($menu);

        DB::transaction(function () use ($menu, $items, $before): void {
            // Children first, unordered: one ordered DELETE over the
            // self-referencing cascade stops early on MySQL and leaves
            // old top-level items behind, which then show up twice.
            MenuItem::query()->where('menu_id', $menu->id)->whereNotNull('parent_id')->delete();
            MenuItem::query()->where('menu_id', $menu->id)->delete();

            foreach ($items as $position => $item) {
                $parent = $this->create($menu, $item, null, $position);

                foreach (array_values((array) ($item['children'] ?? [])) as $childPosition => $child) {
                    /** @var array<string, mixed> $child */
                    $this->create($menu, $child, $parent->id, $childPosition);
                }
            }

            // Documents placed in a menu count as used: the library won't
            // trash them, and replacing one refreshes the website.
            $documents = array_values($menu->items()->whereNotNull('media_id')->get()
                ->map(fn (MenuItem $item): array => ['media_id' => (int) $item->media_id, 'context' => 'Menu · '.$item->label])
                ->all());
            $this->usages->sync($menu, $documents, $menu->name, '/menus');

            $menu->touch();
            $this->audit->record('menu.updated', $menu, ['items' => $before], ['items' => $this->admin($menu->refresh())], label: $menu->name);
        });

        ContentPublished::dispatch(['content', 'menus']);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     *
     * @throws ValidationException
     */
    public function assertPagesExist(array $items): void
    {
        $errors = [];

        foreach ($items as $i => $item) {
            $entries = ["items.{$i}" => $item];

            foreach ((array) ($item['children'] ?? []) as $j => $child) {
                $entries["items.{$i}.children.{$j}"] = (array) $child;
            }

            foreach ($entries as $key => $entry) {
                if (in_array($entry['type'] ?? null, ['page', 'anchor'], true) && ! $this->pages->exists((int) ($entry['page_id'] ?? 0))) {
                    $label = trim((string) ($entry['label'] ?? ''));
                    $errors["{$key}.page_id"] = [$label === ''
                        ? 'Choose a page.'
                        : "“{$label}” points at a page that no longer exists. Choose another page for it, or remove it."];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function create(Menu $menu, array $item, ?int $parentId, int $position): MenuItem
    {
        $type = (string) $item['type'];

        return $menu->items()->create([
            'parent_id' => $parentId,
            'label' => (string) $item['label'],
            'type' => $type,
            'page_id' => in_array($type, ['page', 'anchor'], true) ? (int) $item['page_id'] : null,
            'media_id' => $type === 'document' ? (int) $item['media_id'] : null,
            'anchor' => $type === 'anchor' ? (string) $item['anchor'] : null,
            'url' => $type === 'link' ? (string) $item['url'] : null,
            'is_visible' => (bool) ($item['is_visible'] ?? true),
            'sort_order' => $position + 1,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function build(Menu $menu, bool $public): array
    {
        $items = $menu->items()->get();
        $pages = $this->pages->find(array_values(array_unique(array_filter($items->map(fn (MenuItem $item): ?int => $item->page_id)->all()))));
        $documents = [];

        foreach (array_unique(array_filter($items->map(fn (MenuItem $item): ?int => $item->type === 'document' ? $item->media_id : null)->all())) as $mediaId) {
            $documents[$mediaId] = $this->media->presentDocument($mediaId);
        }

        $rows = [];

        foreach ($items as $item) {
            $page = $item->page_id !== null ? $pages[$item->page_id] ?? null : null;
            $document = $item->media_id !== null ? $documents[$item->media_id] ?? null : null;
            $href = match ($item->type) {
                'page' => $page['path'] ?? null,
                'anchor' => $page !== null ? ($page['path'] === '/' ? '/' : $page['path']).'#'.$item->anchor : null,
                'document' => $document !== null ? self::sitePath($document['url']) : null,
                default => $item->url,
            };

            if ($public && (! $item->is_visible || $href === null || ($page !== null && ! $page['published']))) {
                continue;
            }

            $rows[] = $public
                ? ['id' => $item->id, 'parent_id' => $item->parent_id, 'label' => $item->label, 'href' => (string) $href]
                : [
                    'id' => $item->id,
                    'parent_id' => $item->parent_id,
                    'label' => $item->label,
                    'type' => $item->type,
                    'page_id' => $item->page_id,
                    'page_title' => $page['title'] ?? null,
                    'page_published' => $page['published'] ?? null,
                    'anchor' => $item->anchor,
                    'url' => $item->url,
                    'media_id' => $item->media_id,
                    'document_filename' => $document['filename'] ?? null,
                    'href' => $href,
                    'is_visible' => $item->is_visible,
                ];
        }

        $tree = [];

        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $children = array_values(array_filter($rows, fn (array $child): bool => $child['parent_id'] === $row['id']));
                $tree[] = [...$this->strip($row, $public), 'children' => array_map(fn (array $child): array => $this->strip($child, $public), $children)];
            }
        }

        return $tree;
    }

    /** The site serves /storage on the same domain, so links stay site-relative. */
    private static function sitePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function strip(array $row, bool $public): array
    {
        unset($row['parent_id']);

        if ($public) {
            unset($row['id']);
        }

        return $row;
    }
}
