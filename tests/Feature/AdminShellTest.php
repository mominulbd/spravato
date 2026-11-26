<?php

declare(strict_types=1);

it('serves the admin SPA shell at the root and every admin path', function (string $path): void {
    $this->withoutVite();

    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);
})->with(['/', '/login', '/consultations', '/pages/home/seo', '/upcoming', '/apiary']);

it('leaves the API, Sanctum and the health check to Laravel', function (): void {
    $this->withoutVite();

    $this->getJson('/api/v1/nope')->assertNotFound()->assertDontSee('<div id="app"></div>', false);
    $this->get('/up')->assertOk()->assertDontSee('<div id="app"></div>', false);
});

it('sends old /admin links to the same path at the root', function (string $from, string $to): void {
    $this->get($from)->assertRedirect($to)->assertStatus(301);
})->with([
    ['/admin', '/'],
    ['/admin/login', '/login'],
    ['/admin/consultations/42', '/consultations/42'],
    ['/admin/reset-password/abc?email=joy%40uptms.test', '/reset-password/abc?email=joy%40uptms.test'],
]);

it('serves the admin shell to guests and lets its router send them to /login', function (): void {
    $this->withoutVite();

    $this->get('/consultations')->assertOk();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
