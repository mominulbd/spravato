<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Seo\Http\Controllers\Admin\SeoAuditController;
use Uptms\Seo\Http\Controllers\Admin\SeoController;
use Uptms\Seo\Http\Controllers\Admin\SeoSettingsController;
use Uptms\Seo\Http\Controllers\Admin\SitemapController;

$types = '[a-z_]+';

Route::middleware('permission:seo.edit|seo.manage')->group(function () use ($types): void {
    Route::get('seo/{type}/{id}', [SeoController::class, 'show'])->where(['type' => $types, 'id' => '[0-9]+'])->name('show');
    Route::get('seo/{type}/scores', [SeoController::class, 'scores'])->where('type', $types)->name('scores');
    Route::get('seo-audit', [SeoAuditController::class, 'show'])->name('audit');
    Route::get('seo-audit/export', [SeoAuditController::class, 'export'])->name('audit.export');
});

Route::middleware('permission:seo.edit')->group(function () use ($types): void {
    Route::post('seo/{type}/{id}/analyze', [SeoController::class, 'analyze'])->where(['type' => $types, 'id' => '[0-9]+'])->name('analyze');
    Route::put('seo/{type}/{id}', [SeoController::class, 'update'])->where(['type' => $types, 'id' => '[0-9]+'])->name('update');
});

Route::middleware('permission:seo.manage')->group(function (): void {
    Route::post('seo-audit', [SeoAuditController::class, 'run'])->middleware('throttle:6,1')->name('audit.run');
    Route::get('seo-sitemap', [SitemapController::class, 'show'])->name('sitemap');
    Route::post('seo-sitemap/robots-test', [SitemapController::class, 'test'])->name('robots_test');
    Route::get('seo-settings', [SeoSettingsController::class, 'show'])->name('settings');
    Route::put('seo-settings/{group}', [SeoSettingsController::class, 'update'])->where('group', '[a-z]+')->name('settings.update');
});
