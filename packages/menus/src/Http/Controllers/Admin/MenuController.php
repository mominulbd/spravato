<?php

declare(strict_types=1);

namespace Uptms\Menus\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Uptms\Menus\Models\Menu;
use Uptms\Menus\Services\MenuTree;

/** Figma 17. Saving changes the live site at once, so it needs content.publish (route). */
class MenuController
{
    public function __construct(private readonly MenuTree $tree) {}

    public function index(): JsonResponse
    {
        $menus = Menu::query()->withCount('items')->orderBy('sort_order')->get()->map(fn (Menu $menu): array => [
            'key' => $menu->key,
            'name' => $menu->name,
            'description' => $menu->description,
            'items_count' => (int) $menu->getAttribute('items_count'),
        ]);

        return ApiResponse::success($menus->values()->all(), 'Menus retrieved.');
    }

    public function show(string $key): JsonResponse
    {
        $menu = Menu::query()->where('key', $key)->firstOrFail();

        return ApiResponse::success($this->present($menu), 'Menu retrieved.');
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $menu = Menu::query()->where('key', $key)->firstOrFail();

        $item = fn (string $prefix): array => [
            "{$prefix}.label" => ['required', 'string', 'max:80'],
            "{$prefix}.type" => ['required', 'in:page,anchor,link'],
            "{$prefix}.page_id" => ['nullable', 'required_if:'.$prefix.'.type,page,anchor', 'integer'],
            "{$prefix}.anchor" => ['nullable', 'required_if:'.$prefix.'.type,anchor', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            "{$prefix}.url" => ['nullable', 'required_if:'.$prefix.'.type,link', 'string', 'max:190', 'regex:'.MenuTree::SAFE_URL],
            "{$prefix}.is_visible" => ['sometimes', 'boolean'],
        ];

        $validated = $request->validate([
            'items' => ['present', 'array', 'max:30'],
            ...$item('items.*'),
            'items.*.children' => ['sometimes', 'array', 'max:12'],
            ...$item('items.*.children.*'),
            'items.*.children.*.children' => ['prohibited'],
        ]);

        /** @var list<array<string, mixed>> $items */
        $items = $validated['items'];
        $this->tree->assertPagesExist($items);
        $this->tree->save($menu, $items);

        return ApiResponse::success($this->present($menu->refresh()), 'Menu saved. The website updates within seconds.');
    }

    /** @return array<string, mixed> */
    private function present(Menu $menu): array
    {
        return [
            'key' => $menu->key,
            'name' => $menu->name,
            'description' => $menu->description,
            'items' => $this->tree->admin($menu),
            'preview' => $this->tree->public($menu),
        ];
    }
}
