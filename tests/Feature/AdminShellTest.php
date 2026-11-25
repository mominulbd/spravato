<?php

declare(strict_types=1);

it('serves the admin SPA shell for any admin path', function (string $path): void {
    $this->withoutVite();

    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);
})->with(['/admin', '/admin/login', '/admin/pages/home/seo']);

it('leaves the site root to the Next.js site', function (): void {
    $this->get('/')->assertNotFound();
});

it('sends /login to the admin sign-in page', function (): void {
    $this->get('/login')->assertRedirect('/admin/login')->assertStatus(301);
});

it('serves the admin shell to guests and lets its router send them to /admin/login', function (): void {
    $this->withoutVite();

    $this->get('/admin/consultations')->assertOk();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
