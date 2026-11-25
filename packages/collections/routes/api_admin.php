<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Collections\Http\Controllers\Admin\CollectionController;

// content.edit drafts; the policy adds content.publish for anything the site shows.
Route::middleware('permission:content.edit')->group(function (): void {
    Route::get('collections', [CollectionController::class, 'types'])->name('types');
    Route::get('collections/{type}', [CollectionController::class, 'index'])->name('index');
    Route::post('collections/{type}', [CollectionController::class, 'store'])->name('store');
    Route::put('collections/{type}/reorder', [CollectionController::class, 'reorder'])->name('reorder');
    Route::put('collections/{type}/{id}', [CollectionController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('collections/{type}/{id}', [CollectionController::class, 'destroy'])->whereNumber('id')->name('destroy');
});
