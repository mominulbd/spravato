<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\AuditLog\Http\Controllers\Admin\AuditLogController;

Route::middleware('permission:audit.view')->group(function (): void {
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('index');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('export');
});
