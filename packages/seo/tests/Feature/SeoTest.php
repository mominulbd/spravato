<?php

declare(strict_types=1);

use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Pages\Models\Page;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
    $this->home = Page::query()->where('path', '/')->firstOrFail();
});

it('gives every published page head tags and a JSON-LD graph built from its content', function () {
    $seo = $this->getJson('/api/v1/public/page?path=/tms')->assertOk()->json('data.seo');

    expect($seo['title'])->toBe('Deep TMS for Depression & OCD | UPTMS')
        ->and($seo['canonical'])->toEndWith('/tms')
        ->and($seo['robots']['index'])->toBeTrue()
        ->and($seo['open_graph']['image']['url'])->toBeString()
        ->and(array_column($seo['json_ld'], '@type'))->toContain('MedicalClinic', 'WebSite', 'MedicalWebPage', 'BreadcrumbList', 'FAQPage');

    $clinic = collect($seo['json_ld'])->firstWhere('@type', 'MedicalClinic');
    expect($clinic['telephone'])->toBe('+1-253-234-4199')
        ->and($clinic['address']['postalCode'])->toBe('98466')
        ->and($clinic['openingHoursSpecification'])->not->toBeEmpty();
});

it('saves SEO for staff with seo.edit and the website shows it at once', function () {
    $manager = staffUser('manager');

    asAdmin($manager)->putJson("/api/v1/admin/seo/page/{$this->home->id}", [
        'meta_title' => 'Deep TMS Therapy in University Place, WA %sep% %site%',
        'meta_description' => 'When depression won’t lift, explore Deep TMS with a psychiatric provider near Tacoma. Ask about insurance — your first phone consultation is free.',
        'focus_keyphrase' => 'deep tms',
        'keyphrases' => ['tms therapy tacoma'],
        'schema_disabled' => ['faq'],
    ])->assertOk()
        ->assertJsonPath('data.resolved.title', 'Deep TMS Therapy in University Place, WA | UPTMS')
        ->assertJsonPath('data.meta.focus_keyphrase', 'deep tms');

    $seo = $this->getJson('/api/v1/public/page?path=%2F')->assertOk()->json('data.seo');
    expect($seo['title'])->toBe('Deep TMS Therapy in University Place, WA | UPTMS')
        ->and(array_column($seo['json_ld'], '@type'))->not->toContain('FAQPage');

    expect(AuditLog::query()->where('action', 'seo.updated')->count())->toBe(1);
});

it('scores live while typing without saving', function () {
    $before = DB::table('seo_meta')->get()->toArray();
    $response = asAdmin(staffUser('manager'))->postJson("/api/v1/admin/seo/page/{$this->home->id}/analyze", [
        'meta_title' => str_repeat('Very long title ', 8),
        'focus_keyphrase' => 'deep tms',
    ])->assertOk();

    $checks = collect($response->json('data.analysis.seo'))->keyBy('key');
    expect($checks['title_length']['status'])->toBe('problem')
        ->and($checks['keyphrase_density']['status'])->toBe('good')
        ->and($response->json('data.analysis.title.px'))->toBeGreaterThan(580);

    expect(DB::table('seo_meta')->get()->toArray())->toEqual($before);
});

it('flags a focus keyphrase another page already targets', function () {
    $manager = staffUser('manager');
    $tms = Page::query()->where('path', '/tms')->firstOrFail();
    asAdmin($manager)->putJson("/api/v1/admin/seo/page/{$tms->id}", ['focus_keyphrase' => 'deep tms'])->assertOk();

    $checks = collect(asAdmin($manager)->postJson("/api/v1/admin/seo/page/{$this->home->id}/analyze", ['focus_keyphrase' => 'Deep TMS'])->json('data.analysis.seo'))->keyBy('key');

    expect($checks['keyphrase_unique']['status'])->toBe('problem');
});

it('validates custom JSON-LD and refuses people without seo.edit', function () {
    $manager = staffUser('manager');

    assertErrorCode(asAdmin($manager)->putJson("/api/v1/admin/seo/page/{$this->home->id}", ['schema_custom' => ['name' => 'No type']]), 'VALIDATION_FAILED', 422)
        ->assertJsonValidationErrors('schema_custom');
    asAdmin($manager)->putJson("/api/v1/admin/seo/page/{$this->home->id}", ['schema_custom' => ['@type' => 'MedicalOrganization', 'name' => 'Partner']])->assertOk();

    assertErrorCode(asAdmin(staffUser('staff'))->putJson("/api/v1/admin/seo/page/{$this->home->id}", ['meta_title' => 'x']), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin(staffUser('staff'))->getJson("/api/v1/admin/seo/page/{$this->home->id}"), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($manager)->getJson('/api/v1/admin/seo/page/999999'), 'NOT_FOUND', 404);
    assertErrorCode(asAdmin($manager)->getJson("/api/v1/admin/seo/unknown/{$this->home->id}"), 'NOT_FOUND', 404);
});

it('keeps previews out of search results', function () {
    $token = asAdmin(staffUser('manager'))->postJson("/api/v1/admin/pages/{$this->home->id}/preview")->json('data.token');

    $this->getJson('/api/v1/public/page?path=%2F&preview='.urlencode($token))->assertOk()->assertJsonPath('data.seo.robots.index', false);
});

it('lets seo.manage change title templates and verification codes', function () {
    assertErrorCode(asAdmin(staffUser('manager'))->putJson('/api/v1/admin/seo-settings/general', ['site_name' => 'X']), 'FORBIDDEN', 403);
    $admin = staffUser('admin');

    assertErrorCode(asAdmin($admin)->putJson('/api/v1/admin/seo-settings/general', ['template_page' => 'No title variable']), 'VALIDATION_FAILED', 422);
    asAdmin($admin)->putJson('/api/v1/admin/seo-settings/general', ['separator' => '·', 'template_page' => '%title% %sep% University Place TMS'])
        ->assertOk()->assertJsonPath('data.examples.page', 'TMS therapy · University Place TMS');
    $privacy = Page::query()->where('path', '/privacy')->firstOrFail();
    asAdmin($admin)->putJson("/api/v1/admin/seo/page/{$privacy->id}", ['meta_title' => null])->assertOk();
    asAdmin($admin)->putJson('/api/v1/admin/seo-settings/verification', ['google' => 'abc123-XYZ'])->assertOk();
    asAdmin($admin)->putJson('/api/v1/admin/seo-settings/social', ['x_handle' => 'uptms'])->assertOk()->assertJsonPath('data.values.x_handle', '@uptms');

    $this->getJson('/api/v1/public/seo')->assertOk()->assertJsonPath('data.verification.google', 'abc123-XYZ');
    expect($this->getJson('/api/v1/public/page?path=/privacy')->json('data.seo.title'))->toBe('Privacy policy · University Place TMS');
});

it('turns Google Tag Manager on for the website and keeps it off the form', function () {
    expect($this->getJson('/api/v1/public/seo')->assertOk()->json('data.tag_manager'))->toBeNull();

    assertErrorCode(asAdmin(staffUser('manager'))->putJson('/api/v1/admin/seo-settings/analytics', ['gtm_enabled' => true, 'gtm_container_id' => 'GTM-M4Q7S9NS']), 'FORBIDDEN', 403);
    $admin = staffUser('admin');

    assertErrorCode(asAdmin($admin)->putJson('/api/v1/admin/seo-settings/analytics', ['gtm_enabled' => true, 'gtm_container_id' => '']), 'VALIDATION_FAILED', 422);
    assertErrorCode(asAdmin($admin)->putJson('/api/v1/admin/seo-settings/analytics', ['gtm_enabled' => true, 'gtm_container_id' => 'UA-12345']), 'VALIDATION_FAILED', 422);

    asAdmin($admin)->putJson('/api/v1/admin/seo-settings/analytics', ['gtm_enabled' => true, 'gtm_container_id' => ' gtm-m4q7s9ns '])
        ->assertOk()->assertJsonPath('data.values.gtm_container_id', 'GTM-M4Q7S9NS');

    expect($this->getJson('/api/v1/public/seo')->assertOk()->json('data.tag_manager'))
        ->toBe(['id' => 'GTM-M4Q7S9NS', 'exclude_paths' => ['/contact']]);
    expect(AuditLog::query()->where('action', 'seo.settings_updated')->where('auditable_label', 'analytics')->count())->toBe(1);

    asAdmin($admin)->putJson('/api/v1/admin/seo-settings/analytics', ['gtm_exclude_sensitive' => false])->assertOk();
    expect($this->getJson('/api/v1/public/seo')->json('data.tag_manager.exclude_paths'))->toBe([]);

    asAdmin($admin)->putJson('/api/v1/admin/seo-settings/analytics', ['gtm_enabled' => false])->assertOk();
    expect($this->getJson('/api/v1/public/seo')->json('data.tag_manager'))->toBeNull();
});
