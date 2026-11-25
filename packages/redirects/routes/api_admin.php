<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Redirects\Http\Controllers\Admin\NotFoundController;
use Uptms\Redirects\Http\Controllers\Admin\RedirectController;

Route::middleware('permission:seo.manage')->group(function (): void {
    Route::get('redirects', [RedirectController::class, 'index'])->name('index');
    Route::post('redirects', [RedirectController::class, 'store'])->name('store');
    Route::post('redirects/check', [RedirectController::class, 'check'])->name('check');
    Route::post('redirects/import', [RedirectController::class, 'import'])->name('import');
    Route::get('redirects/export', [RedirectController::class, 'export'])->name('export');
    Route::put('redirects/{redirect}', [RedirectController::class, 'update'])->whereNumber('redirect')->name('update');
    Route::delete('redirects/{redirect}', [RedirectController::class, 'destroy'])->whereNumber('redirect')->name('destroy');

    Route::get('not-found', [NotFoundController::class, 'index'])->name('not_found.index');
    Route::get('not-found/export', [NotFoundController::class, 'export'])->name('not_found.export');
    Route::delete('not-found/ignored', [NotFoundController::class, 'clearIgnored'])->name('not_found.clear');
    Route::post('not-found/{log}/resolve', [NotFoundController::class, 'resolve'])->whereNumber('log')->name('not_found.resolve');
    Route::post('not-found/{log}/ignore', [NotFoundController::class, 'ignore'])->whereNumber('log')->name('not_found.ignore');
    Route::post('not-found/{log}/restore', [NotFoundController::class, 'restore'])->whereNumber('log')->name('not_found.restore');
});
