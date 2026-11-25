<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Settings\Http\Controllers\Public\SettingsController;

Route::get('settings', [SettingsController::class, 'show'])->name('show');
