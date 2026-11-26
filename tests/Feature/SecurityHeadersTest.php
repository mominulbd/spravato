<?php

declare(strict_types=1);

use App\Support\Middleware\SecurityHeaders;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;

/** @return array<string, string> directive => value */
function cspDirectives(string $policy): array
{
    $directives = [];

    foreach (explode('; ', $policy) as $part) {
        [$name, $value] = array_pad(explode(' ', $part, 2), 2, '');
        $directives[$name] = $value;
    }

    return $directives;
}

it('locks API responses down completely', function (): void {
    $response = $this->getJson('/api/v1/health')
        ->assertHeader('Content-Security-Policy', SecurityHeaders::API_POLICY)
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Permissions-Policy');

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('sends the same policy on errors', function (): void {
    $this->getJson('/api/v1/nope')
        ->assertNotFound()
        ->assertHeader('Content-Security-Policy', SecurityHeaders::API_POLICY);
});

it('gives the admin shell a nonce that the Vite tags carry', function (): void {
    $this->withoutVite();

    $response = $this->get('/admin/login')->assertOk();
    $csp = cspDirectives((string) $response->headers->get('Content-Security-Policy'));
    $nonce = app(Vite::class)->cspNonce();

    expect($nonce)->toBeString()->not->toBe('')
        ->and($csp['script-src'])->toBe("'self' 'nonce-{$nonce}'")
        ->and($csp['connect-src'])->toBe("'self'")
        ->and($csp['frame-ancestors'])->toBe("'none'")
        ->and($csp['object-src'])->toBe("'none'")
        ->and($csp['form-action'])->toBe("'self'")
        ->and($csp['default-src'])->toBe("'self'")
        ->and($csp['script-src'])->not->toContain('unsafe');
});

it('stamps the nonce on the built asset tags', function (): void {
    $manifest = public_path('build-csp-test/manifest.json');
    File::ensureDirectoryExists(dirname($manifest));
    File::put($manifest, (string) json_encode([
        'resources/js/admin/main.tsx' => ['file' => 'assets/main-abc.js', 'isEntry' => true, 'src' => 'resources/js/admin/main.tsx'],
    ]));
    app(Vite::class)->useBuildDirectory('build-csp-test')->useHotFile(storage_path('framework/no-hot-file'));

    try {
        $response = $this->get('/admin')->assertOk();
        $nonce = app(Vite::class)->cspNonce();

        expect($response->getContent())->toContain('nonce="'.$nonce.'"')
            ->and((string) $response->headers->get('Content-Security-Policy'))->toContain("'nonce-{$nonce}'");
    } finally {
        File::deleteDirectory(dirname($manifest));
    }
});

it('changes the nonce on every request', function (): void {
    $this->withoutVite();

    $first = $this->get('/admin')->headers->get('Content-Security-Policy');
    $second = $this->get('/admin')->headers->get('Content-Security-Policy');

    expect($first)->not->toBe($second);
});

it('allows the Vite dev server only while it runs', function (): void {
    $hot = storage_path('framework/testing-vite-hot');
    File::put($hot, 'http://[::1]:5173');
    app(Vite::class)->useHotFile($hot);

    try {
        $csp = cspDirectives((string) $this->get('/admin')->headers->get('Content-Security-Policy'));

        expect($csp['script-src'])->toContain('http://[::1]:5173')
            ->and($csp['connect-src'])->toContain('ws://[::1]:5173');
    } finally {
        File::delete($hot);
    }

    $this->app->detectEnvironment(fn (): string => 'production');
    File::put($hot, 'http://localhost:5173');

    try {
        $csp = cspDirectives((string) $this->get('/admin')->headers->get('Content-Security-Policy'));

        expect($csp['script-src'])->not->toContain('5173');
    } finally {
        File::delete($hot);
    }
});

it('sends HSTS over https outside local', function (): void {
    $this->withoutVite();

    $this->get('https://localhost/admin')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    $this->getJson('https://localhost/api/v1/health')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
