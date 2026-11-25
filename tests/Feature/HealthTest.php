<?php

declare(strict_types=1);

it('reports a healthy API in the standard envelope', function (): void {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertHeader('X-Request-Id')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.database', 'ok')
        ->assertJsonStructure(['success', 'message', 'data' => ['status', 'app', 'environment', 'database', 'server_time'], 'meta']);
});

it('echoes a caller-supplied request id', function (): void {
    $this->getJson('/api/v1/health', ['X-Request-Id' => 'next-build-42'])
        ->assertHeader('X-Request-Id', 'next-build-42');
});

it('sends the baseline security headers', function (): void {
    $this->getJson('/api/v1/health')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
