<?php

declare(strict_types=1);

use App\Support\Http\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('sample', fn () => ApiResponse::success(['scope' => 'admin']))->name('show');
