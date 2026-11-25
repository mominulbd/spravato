<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Redirects\Http\Controllers\Public\SiteController;

Route::get('redirects', [SiteController::class, 'rules'])->name('rules');
Route::post('redirects/{redirect}/hit', [SiteController::class, 'hit'])->whereNumber('redirect')->middleware('throttle:redirect-hits')->name('hit');
Route::post('not-found', [SiteController::class, 'notFound'])->middleware('throttle:public-forms-404')->name('not_found');
