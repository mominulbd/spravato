<?php

declare(strict_types=1);

namespace Uptms\Menus;

use App\Support\Packages\ModuleServiceProvider;
use Uptms\Menus\Models\Menu;
use Uptms\Menus\Models\MenuItem;
use Uptms\Menus\Services\MenuTree;
use Uptms\Menus\Sources\MenuSource;
use Uptms\Publishing\Services\BlockSources;

final class MenusServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        MenuTree::class => MenuTree::class,
    ];

    protected array $morphMap = [
        'menu' => Menu::class,
        'menu_item' => MenuItem::class,
    ];

    protected function bootModule(): void
    {
        $this->app->make(BlockSources::class)->register('menus', MenuSource::class);
    }
}
