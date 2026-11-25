<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Uptms\Seo\Http\Controllers\Public\SeoController;
use Uptms\Seo\Http\Controllers\Public\SitemapController;

Route::get('seo', [SeoController::class, 'site'])->name('site');
Route::get('sitemap', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('robots', [SitemapController::class, 'robots'])->name('robots');
