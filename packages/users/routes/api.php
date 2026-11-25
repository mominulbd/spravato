<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Users\Http\Controllers\InvitationController;

Route::middleware('throttle:invitations')->group(function (): void {
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
});
