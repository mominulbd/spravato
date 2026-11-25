<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Menus\Http\Controllers\Public\MenuController;

Route::get('menus', [MenuController::class, 'index'])->name('index');
