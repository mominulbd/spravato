<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Pages\Http\Controllers\Admin\PageController;

// content.edit for the working copy; PagePolicy adds content.publish where the site changes.
Route::middleware('permission:content.edit')->group(function (): void {
    Route::get('pages', [PageController::class, 'index'])->name('index');
    Route::get('pages/block-types', [PageController::class, 'blockTypes'])->name('block_types');
    Route::post('pages', [PageController::class, 'store'])->name('store');
    Route::get('pages/{page}', [PageController::class, 'show'])->whereNumber('page')->name('show');
    Route::put('pages/{page}', [PageController::class, 'updateSettings'])->whereNumber('page')->name('settings');
    Route::put('pages/{page}/content', [PageController::class, 'updateContent'])->whereNumber('page')->name('content');
    Route::post('pages/{page}/publish', [PageController::class, 'publish'])->whereNumber('page')->name('publish');
    Route::post('pages/{page}/schedule', [PageController::class, 'schedule'])->whereNumber('page')->name('schedule');
    Route::delete('pages/{page}/schedule', [PageController::class, 'unschedule'])->whereNumber('page')->name('unschedule');
    Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->whereNumber('page')->name('unpublish');
    Route::get('pages/{page}/revisions', [PageController::class, 'revisions'])->whereNumber('page')->name('revisions');
    Route::post('pages/{page}/revisions/{revision}/restore', [PageController::class, 'restoreRevision'])->whereNumber(['page', 'revision'])->name('revisions.restore');
    Route::post('pages/{page}/preview', [PageController::class, 'preview'])->whereNumber('page')->name('preview');
    Route::delete('pages/{page}', [PageController::class, 'destroy'])->whereNumber('page')->name('destroy');
    Route::post('pages/{id}/restore', [PageController::class, 'restore'])->whereNumber('id')->name('restore');
});
