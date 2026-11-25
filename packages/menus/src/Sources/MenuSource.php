<?php

declare(strict_types=1);

namespace Uptms\Menus\Sources;

use Uptms\Menus\Models\Menu;
use Uptms\Menus\Services\MenuTree;
use Uptms\Publishing\Contracts\BlockSource;

/** The "Section navigation" block lists a menu's links. */
final readonly class MenuSource implements BlockSource
{
    public function __construct(private MenuTree $tree) {}

    public function key(): string
    {
        return 'menus';
    }

    public function resolve(array $params): mixed
    {
        $menu = Menu::query()->where('key', (string) ($params['menu'] ?? ''))->first();

        return $menu instanceof Menu ? $this->tree->public($menu) : [];
    }

    public function options(): array
    {
        return array_values(Menu::query()->orderBy('sort_order')->get()
            ->map(fn (Menu $menu): array => ['value' => $menu->key, 'label' => $menu->name])
            ->all());
    }
}
