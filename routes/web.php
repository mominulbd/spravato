<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Laravel and the Next.js site (site/) share one domain
 * (docs/DEVELOPMENT-PLAN.md §1). Laravel answers /admin, /api, /sanctum,
 * /build, /storage and /up; every other path, starting with `/`, belongs to
 * the site. Every path under /admin belongs to the admin SPA's router; data
 * comes only from /api/v1. Staff sign in at /admin/login.
 */
Route::redirect('/login', '/admin/login', 301)->name('login');

/*
 * The root only reaches Laravel when it owns the whole domain (the Hostinger
 * layout, deploy/README.md); behind the one-domain proxy `/` is the site's.
 * Guests go to sign-in, staff to the dashboard. A 302: the answer depends on
 * the session, so it must not be cached.
 */
Route::get('/', fn (Request $request): RedirectResponse => redirect($request->user() === null ? '/admin/login' : '/admin'))
    ->name('home');

Route::view('/admin/{path?}', 'admin')->where('path', '.*')->name('admin');
