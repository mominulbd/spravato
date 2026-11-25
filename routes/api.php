<?php

declare(strict_types=1);

use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * Domain routes are registered by each package (ModuleServiceProvider).
 * This file holds only what belongs to no module.
 */
Route::prefix('v1')->group(function (): void {
    // Liveness for the admin SPA, the Next.js site and uptime checks (docs/API.md §2).
    Route::get('health', function () {
        try {
            DB::select('select 1');
        } catch (Throwable) {
            return ApiResponse::error(ErrorCode::ServiceUnavailable, 'The database is not reachable.', meta: ['database' => 'unavailable']);
        }

        return ApiResponse::success([
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'database' => 'ok',
            'server_time' => now()->toIso8601String(),
        ]);
    })->middleware('throttle:public')->name('health');
});
