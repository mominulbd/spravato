<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Settings\Http\Controllers\Admin\SettingsController;

Route::middleware('permission:settings.manage')->group(function (): void {
    Route::get('settings', [SettingsController::class, 'show'])->name('show');
    Route::post('settings/cache/clear', [SettingsController::class, 'clearCache'])->name('cache.clear');
    Route::put('settings/hours', [SettingsController::class, 'updateHours'])->name('hours');
    Route::put('settings/{group}', [SettingsController::class, 'update'])->whereAlpha('group')->name('update');
});
