<?php

declare(strict_types=1);

use AminulBD\Package\Laravel\PackageManager;
use Illuminate\Support\Facades\Route;

/*
 * A module's routes land on the prefixes, names and middleware that
 * ModuleServiceProvider promises (docs/ARCHITECTURE.md §3), using the
 * fixture package in tests/fixtures/packages/sample.
 */
beforeEach(function (): void {
    $manager = app(PackageManager::class);
    $manager->register(['fixtures' => base_path('tests/fixtures/packages').'/*/index.php']);
    $manager->load('uptms.sample');

    $package = $manager->get('uptms.sample');
    app()->register($package['provider']);

    Route::getRoutes()->refreshNameLookups();
});

it('mounts routes/api_public.php on /api/v1/public with the public group', function (): void {
    $route = Route::getRoutes()->getByName('public.sample.show');

    expect($route?->uri())->toBe('api/v1/public/sample')
        ->and($route?->gatherMiddleware())->toContain('api', 'public');

    $this->getJson('/api/v1/public/sample')->assertOk()->assertJsonPath('data.scope', 'public');
});

it('mounts routes/api_admin.php on /api/v1/admin behind authentication', function (): void {
    $route = Route::getRoutes()->getByName('admin.sample.show');

    expect($route?->uri())->toBe('api/v1/admin/sample')
        ->and($route?->gatherMiddleware())->toContain('api', 'admin');

    assertErrorCode($this->getJson('/api/v1/admin/sample'), 'UNAUTHENTICATED', 401);
});
