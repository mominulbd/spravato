<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Consultations\Http\Controllers\Public\ConsultationRequestController;

// The website's consultation form, posted server-side by Next.js.
Route::post('consultation-requests', [ConsultationRequestController::class, 'store'])
    ->middleware('throttle:public-forms')
    ->name('store');
