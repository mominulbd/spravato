<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Settings\Seeders\OfficeHoursSeeder;

beforeEach(fn () => $this->seed(OfficeHoursSeeder::class));

it('serves the clinic details the website shell needs, with defaults from Figma', function () {
    $this->getJson('/api/v1/public/settings')->assertOk()
        ->assertJsonPath('data.clinic.phone.href', 'tel:+12532344199')
        ->assertJsonPath('data.hours.short', 'Mon–Fri, 8am–5pm')
        ->assertJsonPath('data.hours.long', 'Mon–Fri · 8:00 AM–5:00 PM')
        ->assertJsonPath('data.hours.days.5.display', 'Closed')
        ->assertJsonPath('data.announcement', null)
        ->assertJsonMissingPath('data.privacy');
});

it('saves a settings tab, audits it and tells the website', function () {
    Event::fake([ContentPublished::class]);

    asAdmin(staffUser('admin'))->putJson('/api/v1/admin/settings/profile', [
        'name' => 'University Place TMS Clinic & General Psychiatry',
        'street' => '4007 Bridgeport Way W, Suite B', 'city' => 'University Place', 'state' => 'WA', 'zip' => '98466',
        'phone' => '253-555-0100', 'email' => 'contact@uptms.com', 'service_area_label' => 'University Place & Greater Tacoma',
        'service_area' => ['Tacoma'], 'social' => [['network' => 'facebook', 'url' => 'https://facebook.com/uptms']],
    ])->assertOk()->assertJsonPath('data.profile.phone', '253-555-0100')->assertJsonPath('data.phone_href', 'tel:+12535550100');

    $this->getJson('/api/v1/public/settings')->assertJsonPath('data.clinic.phone.display', '253-555-0100');
    expect(AuditLog::query()->where('action', 'settings.updated')->where('auditable_label', 'profile')->value('new_values'))
        ->toMatchArray(['phone' => '253-555-0100']);
    Event::assertDispatched(ContentPublished::class);
});

it('validates settings: the crisis disclaimer cannot be emptied and links must be safe', function () {
    $admin = staffUser('admin');

    asAdmin($admin)->putJson('/api/v1/admin/settings/disclaimers', ['crisis' => '', 'crisis_short' => 'x'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['crisis']]);
    asAdmin($admin)->putJson('/api/v1/admin/settings/announcement', ['enabled' => true, 'text' => 'Closed Friday', 'link_label' => 'More', 'link_url' => 'javascript:alert(1)'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['link_url']]);
    asAdmin($admin)->putJson('/api/v1/admin/settings/nope', [])->assertNotFound();
});

it('updates office hours and closures and summarises them', function () {
    $days = collect(range(1, 7))->map(fn (int $day): array => [
        'day' => $day, 'is_open' => $day <= 6, 'opens_at' => $day <= 6 ? ($day === 6 ? '09:00' : '08:00') : null, 'closes_at' => $day <= 6 ? ($day === 6 ? '12:30' : '17:00') : null,
    ])->all();

    asAdmin(staffUser('admin'))->putJson('/api/v1/admin/settings/hours', [
        'days' => $days, 'closures' => [['date' => now()->addWeek()->toDateString(), 'label' => 'Thanksgiving']],
    ])->assertOk()->assertJsonPath('data.hours.short', 'Mon–Fri, 8am–5pm; Sat, 9am–12:30pm');

    $this->getJson('/api/v1/public/settings')->assertJsonPath('data.hours.closures.0.label', 'Thanksgiving');

    $days[0]['closes_at'] = '07:00';
    asAdmin(staffUser('admin'))->putJson('/api/v1/admin/settings/hours', ['days' => $days, 'closures' => []])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['days.0.closes_at']]);
});

it('clears the website cache without touching the rest of the cache, and audits it', function () {
    Event::fake([ContentPublished::class]);
    Cache::forever('unrelated', 'kept');

    asAdmin(staffUser('admin'))->postJson('/api/v1/admin/settings/cache/clear', ['scope' => 'website'])
        ->assertOk()->assertJsonPath('data.scope', 'website')->assertJsonStructure(['data' => ['cleared_at', 'website_connected']]);

    expect(Cache::get('unrelated'))->toBe('kept')
        ->and(AuditLog::query()->where('action', 'settings.cache_cleared')->value('auditable_label'))->toBe('website');
    Event::assertDispatched(ContentPublished::class, fn (ContentPublished $event): bool => $event->tags === ['content']);
});

it('clears the whole application cache and rebuilds settings from the database', function () {
    Event::fake([ContentPublished::class]);
    $admin = staffUser('admin');
    Cache::forever('unrelated', 'gone');
    Cache::forever('settings:all', ['profile' => ['phone' => 'stale']]);

    asAdmin($admin)->postJson('/api/v1/admin/settings/cache/clear', ['scope' => 'application'])->assertOk();

    expect(Cache::has('unrelated'))->toBeFalse();
    asAdmin($admin)->getJson('/api/v1/admin/settings')->assertOk()->assertJsonPath('data.profile.phone', '253-234-4199');
    expect(AuditLog::query()->where('action', 'settings.cache_cleared')->value('auditable_label'))->toBe('application');
    Event::assertDispatched(ContentPublished::class);
});

it('refuses an unknown cache scope', function () {
    asAdmin(staffUser('admin'))->postJson('/api/v1/admin/settings/cache/clear', ['scope' => 'everything'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['scope']]);
});

it('keeps settings to settings.manage', function () {
    assertErrorCode(asAdmin(staffUser('manager'))->getJson('/api/v1/admin/settings'), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin(staffUser('manager'))->putJson('/api/v1/admin/settings/profile', []), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin(staffUser('manager'))->postJson('/api/v1/admin/settings/cache/clear', ['scope' => 'website']), 'FORBIDDEN', 403);
});
