<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Pages\Http\Controllers\Public\PageController;

Route::get('page', [PageController::class, 'show'])->name('show');
Route::get('pages', [PageController::class, 'index'])->name('index');
