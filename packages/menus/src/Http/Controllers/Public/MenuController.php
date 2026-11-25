<?php

declare(strict_types=1);

namespace Uptms\Menus\Http\Controllers\Public;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Uptms\Menus\Models\Menu;
use Uptms\Menus\Services\MenuTree;
use Uptms\Publishing\Services\PublicCache;

/** Every menu keyed by place: header, mobile, section-nav, footer-explore, footer-legal. */
class MenuController
{
    public function index(MenuTree $tree, PublicCache $cache): JsonResponse
    {
        $menus = $cache->remember('menus', fn (): array => Menu::query()->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Menu $menu): array => [$menu->key => $tree->public($menu)])
            ->all());

        return ApiResponse::success((object) $menus, 'Menus retrieved.');
    }
}
