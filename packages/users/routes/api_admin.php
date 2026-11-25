<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Users\Http\Controllers\Admin\UserController;

Route::middleware('permission:users.manage')->group(function (): void {
    Route::get('users', [UserController::class, 'index'])->name('index');
    Route::post('users', [UserController::class, 'store'])->name('store');
    Route::get('users/{user}', [UserController::class, 'show'])->name('show');
    Route::put('users/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::post('users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('invitation');
    Route::post('users/{user}/two-factor/reset', [UserController::class, 'resetTwoFactor'])->name('two_factor_reset');
});
