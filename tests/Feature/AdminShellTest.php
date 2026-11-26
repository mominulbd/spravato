<?php

declare(strict_types=1);

it('serves the admin SPA shell for any admin path', function (string $path): void {
    $this->withoutVite();

    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);
})->with(['/admin', '/admin/login', '/admin/pages/home/seo']);

it('sends guests at the root to the admin sign-in page', function (): void {
    $this->get('/')->assertRedirect('/admin/login')->assertStatus(302);
});

it('sends signed-in staff at the root to the admin dashboard', function (): void {
    $this->actingAs(staffUser())->get('/')->assertRedirect('/admin')->assertStatus(302);
});

it('sends /login to the admin sign-in page', function (): void {
    $this->get('/login')->assertRedirect('/admin/login')->assertStatus(301);
});

it('serves the admin shell to guests and lets its router send them to /admin/login', function (): void {
    $this->withoutVite();

    $this->get('/admin/consultations')->assertOk();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
