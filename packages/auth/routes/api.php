<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Auth\Http\Controllers\AccountController;
use Uptms\Auth\Http\Controllers\PasswordResetController;
use Uptms\Auth\Http\Controllers\SessionController;

Route::post('auth/login', [SessionController::class, 'login'])->middleware('throttle:login')->name('login');
Route::post('auth/two-factor-challenge', [SessionController::class, 'challenge'])->middleware('throttle:two-factor')->name('two_factor.challenge');
Route::post('auth/logout', [SessionController::class, 'logout'])->middleware('auth:sanctum')->name('logout');

Route::post('auth/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:password-reset')->name('password.email');
Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');

// Reachable before 2FA is set up, so the SPA can load the user and run set-up.
Route::middleware(['auth:sanctum', 'active', 'throttle:admin'])->group(function (): void {
    Route::get('auth/me', [SessionController::class, 'me'])->name('me');
    Route::get('auth/two-factor', [AccountController::class, 'twoFactorStatus'])->name('two_factor.show');
    Route::post('auth/two-factor', [AccountController::class, 'beginTwoFactor'])->name('two_factor.begin');
    Route::post('auth/two-factor/confirm', [AccountController::class, 'confirmTwoFactor'])->middleware('throttle:two-factor')->name('two_factor.confirm');
    Route::delete('auth/two-factor', [AccountController::class, 'disableTwoFactor'])->name('two_factor.disable');
    Route::post('auth/two-factor/recovery-codes', [AccountController::class, 'regenerateRecoveryCodes'])->name('two_factor.recovery_codes');
    Route::put('auth/password', [AccountController::class, 'changePassword'])->name('password.change');
});
