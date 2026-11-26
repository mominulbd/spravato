<?php

declare(strict_types=1);

use Database\Seeders\SiteContentSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Uptms\Pages\Models\Page;
use Uptms\Seo\Models\SeoMeta;
use Uptms\Seo\Models\SeoSetting;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
    // The seeded TMS, Contact and Privacy pages start out of search; these tests need several indexable URLs.
    SeoMeta::query()->update(['robots_index' => true, 'in_sitemap' => true]);
});

it('lists every indexable published address with images and dates', function () {
    $paths = array_column($this->getJson('/api/v1/public/sitemap')->assertOk()->json('data'), 'path');

    expect($paths[0])->toBe('/')
        ->and($paths)->toContain('/tms', '/contact', '/privacy');
});

it('leaves out pages hidden in their SEO tab and groups switched off', function () {
    $privacy = Page::query()->where('path', '/privacy')->firstOrFail();
    $admin = asAdmin(staffUser('admin'));

    $admin->putJson("/api/v1/admin/seo/page/{$privacy->id}", ['robots_index' => false])->assertOk();
    $admin->putJson('/api/v1/admin/seo-settings/sitemap', ['include_images' => false])->assertOk();

    $entries = $this->getJson('/api/v1/public/sitemap')->json('data');
    expect(array_column($entries, 'path'))->not->toContain('/privacy')
        ->and(array_merge(...array_column($entries, 'images')))->toBe([]);

    $groups = collect($admin->getJson('/api/v1/admin/seo-sitemap')->assertOk()->json('data.groups'))->keyBy('key');
    expect($groups['page']['count'])->toBe(3);
});

it('reports the robots.txt editor only for robots.txt changes', function () {
    $admin = asAdmin(staffUser('admin'));

    $admin->putJson('/api/v1/admin/seo-settings/sitemap', ['include_images' => false])->assertOk();
    $admin->getJson('/api/v1/admin/seo-sitemap')->assertOk()
        ->assertJsonPath('data.robots.changed_at', null)
        ->assertJsonPath('data.sitemap.read_by_google_at', null)
        ->assertJsonPath('data.sitemap.urls', fn (int $urls): bool => $urls > 0);

    $admin->putJson('/api/v1/admin/seo-settings/sitemap', ['robots_txt' => "User-agent: *\nDisallow: /admin\n"])->assertOk();
    $admin->getJson('/api/v1/admin/seo-sitemap')->assertJsonPath('data.robots.changed_at', fn (?string $at): bool => $at !== null);
});

it('tests a URL against robots.txt like Googlebot', function () {
    $admin = asAdmin(staffUser('admin'));

    $admin->postJson('/api/v1/admin/seo-sitemap/robots-test', ['path' => '/admin/pages'])->assertOk()
        ->assertJsonPath('data.allowed', false)->assertJsonPath('data.rule', 'Disallow: /admin')->assertJsonPath('data.line', 2);
    $admin->postJson('/api/v1/admin/seo-sitemap/robots-test', ['path' => '/tms'])->assertJsonPath('data.allowed', true);
    $admin->postJson('/api/v1/admin/seo-sitemap/robots-test', ['path' => '/tms?preview=abc'])->assertJsonPath('data.allowed', false);
    $admin->postJson('/api/v1/admin/seo-sitemap/robots-test', ['path' => '/api/v1/public/x', 'content' => "User-agent: *\nDisallow: /api/\nAllow: /api/v1/public/"])->assertJsonPath('data.allowed', true);

    assertErrorCode(asAdmin(staffUser('manager'))->getJson('/api/v1/admin/seo-sitemap'), 'FORBIDDEN', 403);
});

it('notifies search engines only when the site is live', function () {
    Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

    $this->artisan('seo:notify')->expectsOutput('SEO_LIVE is off; nothing sent.')->assertSuccessful();
    Http::assertNothingSent();

    config(['seo.live' => true, 'services.frontend.url' => 'https://uptms.com']);
    $this->artisan('seo:notify')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'indexnow')
        && $request['host'] === 'uptms.com'
        && in_array('https://uptms.com/tms', $request['urlList'], true)
        && $request['keyLocation'] === 'https://uptms.com/indexnow-key.txt');
    $key = $this->getJson('/api/v1/public/robots')->assertOk()->json('data.indexnow_key');
    expect($key)->toHaveLength(32)
        ->and(Http::recorded()[0][0]['key'])->toBe($key);
});

it('serves robots.txt from the cache without writing, and refreshes it when saved', function () {
    config(['seo.live' => true]);
    $this->getJson('/api/v1/public/robots')->assertOk()->assertJsonPath('data.indexnow_key', fn (string $key): bool => strlen($key) === 32);
    expect(SeoSetting::query()->where('key', 'indexnow_key')->exists())->toBeFalse();

    DB::enableQueryLog();
    $this->getJson('/api/v1/public/robots')->assertOk();
    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'seo_settings')))->toBeEmpty();

    asAdmin(staffUser('admin'))->putJson('/api/v1/admin/seo-settings/sitemap', ['robots_txt' => "User-agent: *\nDisallow: /private\n"])->assertOk();
    $this->getJson('/api/v1/public/robots')->assertJsonPath('data.text', "User-agent: *\nDisallow: /private");
});
