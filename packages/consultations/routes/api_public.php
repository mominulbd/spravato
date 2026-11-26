<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Consultations\Http\Controllers\Public\ConsultationRequestController;

// The consultation dialog's choices, read by the Next.js server.
Route::get('consultation-form', [ConsultationRequestController::class, 'form'])->name('form');

// The website's consultation dialog, posted server-side by Next.js.
Route::post('consultation-requests', [ConsultationRequestController::class, 'store'])
    ->middleware('throttle:public-forms')
    ->name('store');
