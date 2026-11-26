<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Pages\Models\Page;
use Uptms\Redirects\Models\NotFoundLog;
use Uptms\Redirects\Models\Redirect;
use Uptms\Redirects\Services\RedirectRules;

beforeEach(function () {
    seedSiteContent();
    $this->admin = asAdmin(staffUser('admin'));
});

it('adds redirects, matches exact and wildcard rules, and serves them to the website', function () {
    $this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/Deep-TMS-Therapy/', 'to_path' => '/tms', 'status_code' => 301])->assertCreated()
        ->assertJsonPath('data.from_path', '/deep-tms-therapy');
    $this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/blog/*', 'to_path' => '/resources/$1', 'status_code' => 301])->assertCreated()
        ->assertJsonPath('data.match_type', 'wildcard');

    $rules = app(RedirectRules::class);
    expect($rules->match('/deep-tms-therapy')['to'])->toBe('/tms')
        ->and($rules->match('/blog/first-visit')['to'])->toBe('/resources/first-visit')
        ->and($rules->match('/tms'))->toBeNull();

    $site = $this->getJson('/api/v1/public/redirects')->assertOk()->json('data');
    expect(array_column($site['rules'], 'from'))->toContain('/deep-tms-therapy', '/blog/*')
        ->and($site['paths'])->toContain('/', '/tms', '/contact', '/privacy');
});

it('reports the target before saving, refuses loops and shortens chains', function () {
    $this->admin->postJson('/api/v1/admin/redirects/check', ['from_path' => '/deep-tms-therapy', 'to_path' => '/tms'])->assertOk()
        ->assertJsonPath('data.loop', false)->assertJsonPath('data.target.state', 'ok');
    $this->admin->postJson('/api/v1/admin/redirects/check', ['from_path' => '/x', 'to_path' => '/nowhere'])->assertJsonPath('data.target.state', 'missing');

    $this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/a', 'to_path' => '/b', 'status_code' => 301])->assertCreated();
    $this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/b', 'to_path' => '/tms', 'status_code' => 301])->assertCreated();
    expect(Redirect::query()->where('from_path', '/a')->value('to_path'))->toBe('/tms');

    $this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/tms-old', 'to_path' => '/a', 'status_code' => 301])->assertCreated()
        ->assertJsonPath('data.to_path', '/tms');
    $this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/c', 'to_path' => '/d', 'status_code' => 301])->assertCreated();
    assertErrorCode($this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/d', 'to_path' => '/c', 'status_code' => 301]), 'CONFLICT', 409)
        ->assertJsonStructure(['errors' => ['to_path']]);
});

it('won’t hide a live page behind a redirect, and needs seo.manage', function () {
    assertErrorCode($this->admin->postJson('/api/v1/admin/redirects', ['from_path' => '/tms', 'to_path' => '/contact', 'status_code' => 301]), 'CONFLICT', 409)
        ->assertJsonStructure(['errors' => ['from_path']]);
    assertErrorCode(asAdmin(staffUser('manager'))->getJson('/api/v1/admin/redirects'), 'FORBIDDEN', 403);
});

it('adds a 301 when a live page moves, and removes it when it moves back', function () {
    $page = Page::query()->where('path', '/tms')->firstOrFail();
    $manager = asAdmin(staffUser('manager'));
    $settings = fn (string $slug): array => ['title' => $page->title, 'slug' => $slug, 'template' => $page->template->value];

    $manager->putJson("/api/v1/admin/pages/{$page->id}", $settings('deep-tms'))->assertOk();
    expect(Redirect::query()->where('from_path', '/tms')->value('to_path'))->toBe('/deep-tms')
        ->and(Redirect::query()->where('from_path', '/tms')->value('source'))->toBe('slug_change');

    $manager->putJson("/api/v1/admin/pages/{$page->id}", $settings('tms'))->assertOk();
    expect(Redirect::query()->where('from_path', '/tms')->exists())->toBeFalse()
        ->and(Redirect::query()->where('from_path', '/deep-tms')->value('to_path'))->toBe('/tms');
});

it('records 404s without IP addresses, suggests targets and fixes them in one click', function () {
    config(['services.frontend.url' => 'https://uptms.com']);

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/v1/public/not-found', ['path' => '/tms-treatment', 'referrer' => 'https://www.google.com/search?q=tms'])->assertOk();
    }
    $this->postJson('/api/v1/public/not-found', ['path' => '/wp-login.php'])->assertOk();
    $this->postJson('/api/v1/public/not-found', ['path' => '/old-page', 'referrer' => 'https://uptms.com/tms?x=1'])->assertOk();

    $log = NotFoundLog::query()->where('path', '/tms-treatment')->sole();
    expect($log->hits)->toBe(3)->and($log->google_hits)->toBe(3)
        ->and(NotFoundLog::query()->where('path', '/wp-login.php')->value('is_bot'))->toBeTrue()
        ->and(NotFoundLog::query()->where('path', '/old-page')->value('referrer_path'))->toBe('/tms')
        ->and(collect(DB::getSchemaBuilder()->getColumnListing('not_found_logs'))->contains(fn ($c) => str_contains($c, 'ip')))->toBeFalse();

    $list = $this->admin->getJson('/api/v1/admin/not-found')->assertOk();
    $row = collect($list->json('data'))->firstWhere('path', '/tms-treatment');
    expect($row['suggestion']['path'])->toBe('/tms')
        ->and($list->json('meta.stats.from_google'))->toBe(3);

    $this->admin->postJson("/api/v1/admin/not-found/{$log->id}/resolve", ['to_path' => '/tms'])->assertOk();
    expect($log->refresh()->status)->toBe('resolved')
        ->and(app(RedirectRules::class)->match('/tms-treatment')['to'])->toBe('/tms');

    // A redirected address is no longer logged as missing.
    $this->postJson('/api/v1/public/not-found', ['path' => '/tms-treatment'])->assertOk();
    expect($log->refresh()->hits)->toBe(3);

    // Every status change is in the audit log.
    $this->admin->postJson("/api/v1/admin/not-found/{$log->id}/restore")->assertOk();
    expect(AuditLog::query()->whereIn('action', ['not_found.resolved', 'not_found.restored'])->orderBy('id')->get(['action', 'old_values', 'new_values'])->toArray())->toMatchArray([
        ['action' => 'not_found.resolved', 'old_values' => ['status' => 'open'], 'new_values' => ['path' => '/tms-treatment', 'status' => 'resolved', 'to_path' => '/tms', 'status_code' => 301]],
        ['action' => 'not_found.restored', 'old_values' => ['status' => 'resolved'], 'new_values' => ['path' => '/tms-treatment', 'status' => 'open']],
    ]);
});

it('accepts reports only from the website once a site token is set', function () {
    config(['services.frontend.site_token' => 'site-secret']);
    $redirect = Redirect::query()->create(['from_path' => '/old', 'to_path' => '/tms', 'status_code' => 301]);

    assertErrorCode($this->postJson('/api/v1/public/not-found', ['path' => '/x']), 'FORBIDDEN', 403);
    assertErrorCode($this->postJson("/api/v1/public/redirects/{$redirect->id}/hit"), 'FORBIDDEN', 403);
    $this->postJson("/api/v1/public/redirects/{$redirect->id}/hit", [], ['X-Uptms-Site-Token' => 'site-secret'])->assertOk();

    expect($redirect->refresh()->hits)->toBe(1);
});

it('refuses reports in production while no site token is configured', function () {
    config(['services.frontend.site_token' => null]);
    $redirect = Redirect::query()->create(['from_path' => '/old', 'to_path' => '/tms', 'status_code' => 301]);
    $this->postJson('/api/v1/public/not-found', ['path' => '/x'])->assertOk();

    app()->detectEnvironment(fn (): string => 'production');
    // Production turns CSRF checks back on; the website's server calls carry no session anyway.
    $this->withoutMiddleware(ValidateCsrfToken::class);

    assertErrorCode($this->postJson('/api/v1/public/not-found', ['path' => '/y']), 'FORBIDDEN', 403);
    assertErrorCode($this->postJson("/api/v1/public/redirects/{$redirect->id}/hit"), 'FORBIDDEN', 403);
});

it('prunes old daily counts and 404 addresses nobody has tried for months', function () {
    $redirect = Redirect::query()->forceCreate(['from_path' => '/old', 'to_path' => '/tms', 'status_code' => 301, 'hits' => 9]);
    DB::table('redirect_hits')->insert([
        ['redirect_id' => $redirect->id, 'day' => now()->subDays(91)->toDateString(), 'hits' => 5],
        ['redirect_id' => $redirect->id, 'day' => now()->subDays(10)->toDateString(), 'hits' => 4],
    ]);
    $stale = NotFoundLog::query()->forceCreate(['path' => '/stale', 'hits' => 1, 'first_seen_at' => now()->subDays(200), 'last_seen_at' => now()->subDays(181)]);
    $fresh = NotFoundLog::query()->forceCreate(['path' => '/fresh', 'hits' => 1, 'first_seen_at' => now()->subDays(200), 'last_seen_at' => now()->subDays(2)]);
    DB::table('not_found_hits')->insert([
        ['not_found_log_id' => $fresh->id, 'day' => now()->subDays(100)->toDateString(), 'hits' => 1, 'google_hits' => 0],
        ['not_found_log_id' => $fresh->id, 'day' => now()->subDays(2)->toDateString(), 'hits' => 1, 'google_hits' => 0],
    ]);

    $this->artisan('redirects:prune')->assertSuccessful();

    expect(DB::table('redirect_hits')->sum('hits'))->toEqual(4)
        ->and($redirect->refresh()->hits)->toBe(9)
        ->and(NotFoundLog::query()->pluck('path')->all())->toBe(['/fresh'])
        ->and(DB::table('not_found_hits')->count())->toBe(1)
        ->and(NotFoundLog::query()->find($stale->id))->toBeNull();
});

it('imports redirects from CSV and reports bad rows', function () {
    $csv = UploadedFile::fake()->createWithContent('redirects.csv', "from,to,type\n/old-team,/#care-team,301\n/covid-update,,410\n/tms,/contact,301\n");

    $this->admin->post('/api/v1/admin/redirects/import', ['file' => $csv], ['Accept' => 'application/json'])->assertOk()
        ->assertJsonPath('data.imported', 2)->assertJsonCount(1, 'data.errors');
    expect(Redirect::query()->where('from_path', '/covid-update')->value('status_code'))->toBe(410);
});

it('tells the SEO audit about missing addresses people keep visiting, not scanners', function () {
    foreach (range(1, 4) as $i) {
        $this->postJson('/api/v1/public/not-found', ['path' => '/ocd-treatment'])->assertOk();
        $this->postJson('/api/v1/public/not-found', ['path' => '/wp-login.php'])->assertOk();
    }
    $this->postJson('/api/v1/public/not-found', ['path' => '/typo'])->assertOk();

    $this->artisan('seo:audit')->assertSuccessful();

    $issue = collect($this->admin->getJson('/api/v1/admin/seo-audit')->json('data.issues'))->firstWhere('key', 'not_found_visited');
    expect($issue)->toMatchArray([
        'severity' => 'urgent',
        'title' => 'A removed page still gets visits',
        'detail' => '/ocd-treatment → 404 · 4 visits this week',
        'count' => 1,
        'action' => ['label' => 'Redirect', 'to' => '/seo/404'],
    ]);
});
