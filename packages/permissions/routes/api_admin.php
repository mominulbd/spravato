<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Permissions\Http\Controllers\Admin\RoleController;

// Admins see the matrix (Figma 26); only roles.manage changes it.
Route::middleware('permission:users.manage|roles.manage')->group(function (): void {
    Route::get('permissions', [RoleController::class, 'permissions'])->name('registry');
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');
});

Route::middleware('permission:roles.manage')->group(function (): void {
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::post('roles/require-two-factor', [RoleController::class, 'requireTwoFactor'])->name('roles.require_two_factor');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
});
