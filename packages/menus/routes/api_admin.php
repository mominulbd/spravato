<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Menus\Http\Controllers\Admin\MenuController;

Route::middleware('permission:content.edit')->group(function (): void {
    Route::get('menus', [MenuController::class, 'index'])->name('index');
    Route::get('menus/{key}', [MenuController::class, 'show'])->where('key', '[a-z0-9-]+')->name('show');
});

Route::put('menus/{key}', [MenuController::class, 'update'])->where('key', '[a-z0-9-]+')->middleware('permission:content.publish')->name('update');
