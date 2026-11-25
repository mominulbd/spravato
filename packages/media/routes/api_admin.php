<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Media\Http\Controllers\Admin\MediaController;
use Uptms\Media\Http\Controllers\Admin\MediaFolderController;

// Editors browse and pick; media.manage changes the library (MediaPolicy).
Route::middleware('permission:media.manage|content.edit')->group(function (): void {
    Route::get('media', [MediaController::class, 'index'])->name('index');
    Route::get('media/trash', [MediaController::class, 'trash'])->name('trash');
    Route::get('media/{media}', [MediaController::class, 'show'])->whereNumber('media')->name('show');
    Route::get('media/{media}/download', [MediaController::class, 'download'])->whereNumber('media')->name('download');
    Route::get('media-folders', [MediaFolderController::class, 'index'])->name('folders.index');
});

Route::middleware('permission:media.manage')->group(function (): void {
    Route::post('media', [MediaController::class, 'store'])->middleware('throttle:media-uploads')->name('store');
    Route::post('media/bulk', [MediaController::class, 'bulk'])->name('bulk');
    Route::put('media/{media}', [MediaController::class, 'update'])->whereNumber('media')->name('update');
    Route::post('media/{media}/replace', [MediaController::class, 'replace'])->whereNumber('media')->middleware('throttle:media-uploads')->name('replace');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('destroy');
    Route::post('media/{id}/restore', [MediaController::class, 'restore'])->whereNumber('id')->name('restore');

    Route::post('media-folders', [MediaFolderController::class, 'store'])->name('folders.store');
    Route::put('media-folders/{folder}', [MediaFolderController::class, 'update'])->name('folders.update');
    Route::delete('media-folders/{folder}', [MediaFolderController::class, 'destroy'])->name('folders.destroy');
});
