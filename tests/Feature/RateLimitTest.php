<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Every API route has a named limiter (docs/API.md §3, docs/ARCHITECTURE.md §9).
 * The audit below fails when a new route forgets one; the cases after it
 * check each anonymous limiter lets traffic through and then answers 429 in
 * the standard envelope.
 */

/** Use up a named limiter for the test client (127.0.0.1) without sending every request. */
function exhaustLimiter(string $name, string $key, int $attempts): void
{
    RateLimiter::increment(md5($name.$key), 60, $attempts);
}

it('throttles every API route', function (): void {
    $router = app(Router::class);

    $unthrottled = collect($router->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
        ->reject(fn (Route $route): bool => collect($router->gatherRouteMiddleware($route))
            ->contains(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, ThrottleRequests::class)))
        ->map(fn (Route $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($unthrottled)->toBe([]);
});

it('serves public reads, then throttles them per address', function (): void {
    $this->getJson('/api/v1/public/menus')->assertOk()->assertHeader('X-RateLimit-Limit', '300');

    exhaustLimiter('public', '127.0.0.1', 300);

    assertErrorCode($this->getJson('/api/v1/public/menus'), 'RATE_LIMITED', 429)->assertHeader('Retry-After');
    assertErrorCode($this->getJson('/api/v1/health'), 'RATE_LIMITED', 429);
});

it('allows five password-reset requests a minute, then throttles', function (): void {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@uptms.test'])->assertOk();
    }

    assertErrorCode($this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@uptms.test']), 'RATE_LIMITED', 429);
    assertErrorCode($this->postJson('/api/v1/auth/reset-password', ['token' => 'x', 'email' => 'nobody@uptms.test']), 'RATE_LIMITED', 429);
});

it('allows ten invitation look-ups a minute, then throttles', function (): void {
    foreach (range(1, 10) as $_) {
        expect($this->getJson('/api/v1/invitations/not-a-token')->status())->not->toBe(429);
    }

    assertErrorCode($this->getJson('/api/v1/invitations/not-a-token'), 'RATE_LIMITED', 429);
    assertErrorCode($this->postJson('/api/v1/invitations/not-a-token/accept'), 'RATE_LIMITED', 429);
});

it('throttles the site\'s 404 reports and redirect hits per visitor', function (): void {
    expect($this->postJson('/api/v1/public/not-found', ['path' => '/old-page'])->status())->not->toBe(429);

    exhaustLimiter('public-forms-404', '127.0.0.1', 60);
    exhaustLimiter('redirect-hits', '127.0.0.1', 60);

    assertErrorCode($this->postJson('/api/v1/public/not-found', ['path' => '/old-page']), 'RATE_LIMITED', 429);
    assertErrorCode($this->postJson('/api/v1/public/redirects/1/hit'), 'RATE_LIMITED', 429);
});

it('throttles sign-out like the rest of the admin', function (): void {
    $user = staffUser();

    $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertOk();

    exhaustLimiter('admin', (string) $user->id, 240);

    assertErrorCode($this->actingAs($user)->postJson('/api/v1/auth/logout'), 'RATE_LIMITED', 429);
});
