<?php

declare(strict_types=1);

use Database\Seeders\SiteContentSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Pages\Models\Page;
use Uptms\Seo\Models\SeoAudit;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
    $this->admin = asAdmin(staffUser('admin'));
});

/** @return array<string, mixed> the issue with this key in the latest audit */
function auditIssue(string $key): array
{
    return collect(SeoAudit::query()->latest('id')->firstOrFail()->issues)->firstWhere('key', $key);
}

it('checks every published URL and scores the site like the overview shows it', function () {
    $this->artisan('seo:audit')->assertSuccessful();

    $audit = SeoAudit::query()->sole();
    $overview = $this->admin->getJson('/api/v1/admin/seo-audit')->assertOk();

    expect($audit->status)->toBe('done')
        ->and($audit->trigger)->toBe('schedule')
        ->and($audit->urls)->toBeGreaterThanOrEqual(4)
        ->and($audit->indexed)->toBeNull()
        ->and($overview->json('data.audit.score'))->toBe($audit->score)->toBeBetween(1, 100)
        ->and($overview->json('data.running'))->toBeFalse()
        ->and($overview->json('data.connections'))->toBe(['search_console' => false, 'core_web_vitals' => false])
        ->and(auditIssue('duplicates')['severity'])->toBe('passed')
        ->and(auditIssue('duplicates')['detail'])->toBe("Checked {$audit->indexable} URLs")
        ->and(auditIssue('not_found_visited')['severity'])->toBe('passed');

    // Problems first, then improvements, then what passed.
    $order = array_map(fn (array $i): int => ['urgent' => 0, 'warning' => 1, 'notice' => 2, 'passed' => 3][$i['severity']], $audit->issues);
    expect($order)->toBe(collect($order)->sort()->values()->all());
});

it('finds long and duplicate titles, links each page to its SEO tab, and counts fixes this week', function () {
    $tms = Page::query()->where('path', '/tms')->firstOrFail();
    $contact = Page::query()->where('path', '/contact')->firstOrFail();
    $long = 'Deep TMS therapy for depression and OCD in University Place and Tacoma, Washington';

    $this->admin->putJson("/api/v1/admin/seo/page/{$tms->id}", ['meta_title' => $long])->assertOk();
    $this->admin->putJson("/api/v1/admin/seo/page/{$contact->id}", ['meta_title' => $long])->assertOk();
    $this->artisan('seo:audit')->assertSuccessful();
    $before = SeoAudit::query()->latest('id')->firstOrFail()->score;

    $titles = auditIssue('title_long');
    expect($titles['severity'])->toBe('warning')
        ->and($titles['count'])->toBe(2)
        ->and(collect($titles['items'])->firstWhere('path', '/tms'))->toMatchArray(['key' => "page:{$tms->id}", 'admin_path' => "/pages/{$tms->id}?tab=seo"])
        ->and(auditIssue('duplicates')['count'])->toBe(2);

    $this->admin->putJson("/api/v1/admin/seo/page/{$tms->id}", ['meta_title' => null])->assertOk();
    $this->admin->putJson("/api/v1/admin/seo/page/{$contact->id}", ['meta_title' => null])->assertOk();
    $this->artisan('seo:audit')->assertSuccessful();

    $overview = $this->admin->getJson('/api/v1/admin/seo-audit')->assertOk();
    expect($overview->json('data.audit.score'))->toBeGreaterThan($before)
        ->and($overview->json('data.fixed_this_week'))->toBe(4)
        ->and(collect($overview->json('data.issues'))->firstWhere('key', 'title_long')['severity'])->toBe('passed');

    // Audits older than a week no longer count as fixes, and give the trend.
    SeoAudit::query()->where('id', '<', $overview->json('data.audit.id'))->update(['started_at' => now()->subDays(8)]);
    $this->admin->getJson('/api/v1/admin/seo-audit')->assertJsonPath('data.fixed_this_week', 0)->assertJsonPath('data.audit.previous_score', $before);
});

it('flags pages robots.txt hides from Google and images without alt text', function () {
    $this->admin->putJson('/api/v1/admin/seo-settings/sitemap', ['robots_txt' => "User-agent: *\nDisallow: /tms\n"])->assertOk();
    $this->artisan('seo:audit')->assertSuccessful();

    expect(auditIssue('robots_blocked'))->toMatchArray(['severity' => 'urgent', 'count' => 1, 'detail' => 'TMS therapy', 'action' => ['label' => 'Fix', 'to' => '/seo/sitemap']]);
});

it('runs on request in the background, once at a time, and records who asked', function () {
    // seo.edit reads the audit; running one on demand is seo.manage.
    assertErrorCode(asAdmin(staffUser('manager'))->postJson('/api/v1/admin/seo-audit'), 'FORBIDDEN', 403);
    $manager = asAdmin(staffUser('admin'));

    $manager->postJson('/api/v1/admin/seo-audit')->assertStatus(202);

    expect(SeoAudit::query()->sole())->status->toBe('done')->trigger->toBe('manual')
        ->and(AuditLog::query()->where('action', 'seo.audit_run')->count())->toBe(1);

    // A run still in progress is reused rather than doubled.
    $running = SeoAudit::query()->create(['status' => 'running', 'trigger' => 'manual', 'started_at' => now()]);
    $manager->postJson('/api/v1/admin/seo-audit')->assertStatus(202)->assertJsonPath('data.running', true);
    $manager->getJson('/api/v1/admin/seo-audit')->assertJsonPath('data.running', true);
    expect(SeoAudit::query()->count())->toBe(2);

    // … unless its worker died.
    $running->forceFill(['started_at' => now()->subMinutes(SeoAudit::TIMEOUT_MINUTES + 1)])->save();
    $manager->postJson('/api/v1/admin/seo-audit')->assertStatus(202);
    expect($running->refresh()->status)->toBe('failed')->and(SeoAudit::query()->where('status', 'done')->count())->toBe(2);
});

it('exports the findings as CSV and is closed to people without SEO access', function () {
    $this->artisan('seo:audit')->assertSuccessful();

    $csv = $this->admin->get('/api/v1/admin/seo-audit/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
    expect($csv)->toStartWith('severity,issue,affected,address,checked_at')->toContain('passed,"No duplicate titles or descriptions"');

    $staff = asAdmin(staffUser('staff'));
    assertErrorCode($staff->getJson('/api/v1/admin/seo-audit'), 'FORBIDDEN', 403);
    assertErrorCode($staff->postJson('/api/v1/admin/seo-audit'), 'FORBIDDEN', 403);
    assertErrorCode($staff->getJson('/api/v1/admin/seo-audit/export'), 'FORBIDDEN', 403);
});

it('adds Search Console numbers and Core Web Vitals when connected', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    config([
        'seo.crux_key' => 'test-key',
        'seo.search_console.property' => 'sc-domain:uptms.com',
        'seo.search_console.credentials' => json_encode(['client_email' => 'seo@uptms.iam.gserviceaccount.com', 'private_key' => $pem]),
    ]);
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
        'searchconsole.googleapis.com/*' => Http::sequence()->push(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'NEUTRAL']]])->whenEmpty(Http::response(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS']]])),
        'www.googleapis.com/webmasters/*' => function (Request $request) {
            return Http::response(['rows' => ($request['dimensions'] ?? []) === []
                ? [['keys' => [], 'clicks' => 1284, 'impressions' => 40210, 'ctr' => 0.03, 'position' => 8.43]]
                : [['keys' => ['tms therapy tacoma'], 'clicks' => 214, 'impressions' => 3000, 'ctr' => 0.07, 'position' => 4.12]]]);
        },
        'chromeuxreport.googleapis.com/*' => Http::response(['record' => ['metrics' => [
            'largest_contentful_paint' => ['percentiles' => ['p75' => 1800]],
            'interaction_to_next_paint' => ['percentiles' => ['p75' => 120]],
            'cumulative_layout_shift' => ['percentiles' => ['p75' => '0.02']],
        ]]]),
    ]);

    $this->artisan('seo:audit')->assertSuccessful();
    $overview = $this->admin->getJson('/api/v1/admin/seo-audit')->assertOk();

    expect($overview->json('data.audit.indexed'))->toBe($overview->json('data.audit.indexable') - 1)
        ->and($overview->json('data.search'))->toEqual(['clicks' => 1284, 'impressions' => 40210, 'position' => 8.4, 'queries' => [['query' => 'tms therapy tacoma', 'clicks' => 214, 'position' => 4.1]]])
        ->and($overview->json('data.vitals.passing'))->toBeTrue()
        ->and(array_column($overview->json('data.vitals.metrics'), 'rating', 'key'))->toBe(['lcp' => 'good', 'inp' => 'good', 'cls' => 'good']);
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'chromeuxreport') && $r['formFactor'] === 'PHONE');
});

it('leaves Core Web Vitals empty when Google has too little data', function () {
    config(['seo.crux_key' => 'test-key']);
    Http::fake(['chromeuxreport.googleapis.com/*' => Http::response(['error' => ['code' => 404]], 404)]);

    $this->artisan('seo:audit')->assertSuccessful();

    $this->admin->getJson('/api/v1/admin/seo-audit')->assertJsonPath('vitals', null)->assertJsonPath('data.connections.core_web_vitals', true);
});
