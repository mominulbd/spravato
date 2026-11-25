<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Collections\Http\Controllers\Public\CollectionController;

Route::get('collections/{type}', [CollectionController::class, 'index'])->name('index');
