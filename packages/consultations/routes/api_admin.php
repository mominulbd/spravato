<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Consultations\Http\Controllers\Admin\ConsultationRequestController;

Route::middleware('permission:consultations.view')->group(function (): void {
    Route::get('consultation-requests', [ConsultationRequestController::class, 'index'])->name('index');
    Route::get('consultation-requests/overview', [ConsultationRequestController::class, 'overview'])->name('overview');
    Route::get('consultation-requests/waiting', [ConsultationRequestController::class, 'waiting'])->name('waiting');
    Route::get('consultation-requests/{consultation}', [ConsultationRequestController::class, 'show'])->whereNumber('consultation')->name('show');
});

Route::middleware('permission:consultations.update')->group(function (): void {
    Route::post('consultation-requests/bulk', [ConsultationRequestController::class, 'bulk'])->name('bulk');
    Route::patch('consultation-requests/{consultation}', [ConsultationRequestController::class, 'update'])->whereNumber('consultation')->name('update');
    Route::post('consultation-requests/{consultation}/notes', [ConsultationRequestController::class, 'note'])->whereNumber('consultation')->name('notes');
});

Route::middleware('permission:consultations.export')->group(function (): void {
    Route::get('consultation-requests/export', [ConsultationRequestController::class, 'export'])->name('export');
    Route::delete('consultation-requests/{consultation}', [ConsultationRequestController::class, 'destroy'])->whereNumber('consultation')->name('destroy');
});
