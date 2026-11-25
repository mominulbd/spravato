<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Laravel and the Next.js site (site/) share one domain
 * (docs/DEVELOPMENT-PLAN.md §1). Laravel answers /admin, /api, /sanctum,
 * /build, /storage and /up; every other path, starting with `/`, belongs to
 * the site. Every path under /admin belongs to the admin SPA's router; data
 * comes only from /api/v1. Staff sign in at /admin/login.
 */
Route::redirect('/login', '/admin/login', 301)->name('login');

Route::view('/admin/{path?}', 'admin')->where('path', '.*')->name('admin');
