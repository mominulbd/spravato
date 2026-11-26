<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The admin SPA owns the root of Laravel's origin: staff sign in at /login
 * and every path that is not /api, /sanctum, /build, /storage or /up belongs
 * to the SPA's router; data comes only from /api/v1. The public site
 * (spravato-app) runs on its own origin (deploy/README.md).
 */

// The admin used to live under /admin: links in emails sent before the move
// (invitations, password resets, new-request alerts) keep working.
Route::get('/admin/{path?}', function (Request $request, ?string $path = null): RedirectResponse {
    $query = $request->getQueryString();

    return redirect('/'.($path ?? '').($query === null ? '' : '?'.$query), 301);
})->where('path', '.*');

Route::view('/login', 'admin')->name('login');

Route::view('/{path?}', 'admin')
    ->where('path', '^(?!(api|sanctum|build|storage)(/|$)|up$).*')
    ->name('admin');
