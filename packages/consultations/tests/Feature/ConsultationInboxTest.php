<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Models\ConsultationNote;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Permissions\Models\Permission;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;

function consultation(array $attributes = [], ConsultationStatus $status = ConsultationStatus::New, ?int $assignedTo = null): ConsultationRequest
{
    $factory = ConsultationRequest::factory()->status($status);

    return ($assignedTo === null ? $factory : $factory->assignedTo($assignedTo))->create($attributes);
}

it('lists requests with masked contact details and tab counts', function () {
    consultation(['name' => 'Daniel Reyes', 'phone' => '253-555-0198']);
    consultation(['name' => 'Maria Lopez'], ConsultationStatus::Contacted);
    consultation(['name' => 'Spam Bot'], ConsultationStatus::Spam);

    $response = asAdmin(staffUser('manager'))->getJson('/api/v1/admin/consultation-requests?filter[status]=new')->assertOk();

    $response->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.contact', 'Daniel R. · (253) •••-0198')
        ->assertJsonPath('meta.counts.all', 3)
        ->assertJsonPath('meta.counts.new', 1)
        ->assertJsonPath('meta.counts.spam', 1)
        ->assertJsonPath('meta.can.export', false);
    expect($response->json('data.0'))->not->toHaveKeys(['name', 'phone', 'email', 'message']);
});

it('finds encrypted requests by name, phone, last four digits or reference', function () {
    $daniel = consultation(['name' => 'Daniel Reyes', 'phone' => '(253) 555-0198', 'email' => 'd.reyes@example.com']);
    consultation(['name' => 'Maria Lopez', 'phone' => '(206) 555-4471', 'email' => null]);
    $admin = asAdmin(staffUser('admin'));

    foreach (['daniel', 'Reyes daniel', '253 555 0198', '+1 253-555-0198', '0198', 'D.Reyes@example.com', $daniel->reference] as $term) {
        $admin->getJson('/api/v1/admin/consultation-requests?search='.urlencode((string) $term))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $daniel->id);
    }

    $admin->getJson('/api/v1/admin/consultation-requests?search=dan')->assertOk()->assertJsonCount(0, 'data');
});

it('shows staff only the requests assigned to them', function () {
    $staff = staffUser('staff');
    $mine = consultation(assignedTo: $staff->id);
    $other = consultation();

    asAdmin($staff)->getJson('/api/v1/admin/consultation-requests')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)->assertJsonPath('meta.counts.all', 1);
    asAdmin($staff)->getJson("/api/v1/admin/consultation-requests/{$mine->id}")->assertOk();
    assertErrorCode(asAdmin($staff)->getJson("/api/v1/admin/consultation-requests/{$other->id}"), 'NOT_FOUND', 404);
    assertErrorCode(asAdmin($staff)->patchJson("/api/v1/admin/consultation-requests/{$other->id}", ['status' => 'contacted']), 'NOT_FOUND', 404);
});

it('refuses people without consultation permissions', function () {
    $request = consultation();
    $role = Role::query()->create(['slug' => 'writer', 'name' => 'Writer', 'description' => '', 'requires_two_factor' => false]);
    $role->permissions()->sync(Permission::query()->whereIn('key', ['admin.access', 'content.edit'])->pluck('id'));
    $writer = User::factory()->create(['role_id' => $role->id]);

    assertErrorCode($this->getJson('/api/v1/admin/consultation-requests'), 'UNAUTHENTICATED', 401);
    assertErrorCode(asAdmin($writer)->getJson('/api/v1/admin/consultation-requests'), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($writer)->getJson("/api/v1/admin/consultation-requests/{$request->id}"), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($writer)->getJson('/api/v1/admin/consultation-requests/overview'), 'FORBIDDEN', 403);
});

it('audits every opening of a request', function () {
    $request = consultation(['name' => 'Daniel Reyes', 'phone' => '(253) 555-0198']);
    $manager = staffUser('manager', ['name' => 'Joy Kiongo']);

    asAdmin($manager)->getJson("/api/v1/admin/consultation-requests/{$request->id}")->assertOk()
        ->assertJsonPath('data.name', 'Daniel Reyes')
        ->assertJsonPath('data.phone_href', 'tel:+12535550198')
        ->assertJsonPath('data.privacy.view_count', 1)
        ->assertJsonPath('data.activity.0.label', 'Viewed by Joy Kiongo');
    asAdmin($manager)->getJson("/api/v1/admin/consultation-requests/{$request->id}")->assertJsonPath('data.privacy.view_count', 2);

    $views = AuditLog::query()->where('action', 'consultation.viewed')->get();
    expect($views)->toHaveCount(2)
        ->and($views->first()->user_id)->toBe($manager->id)
        ->and($views->first()->auditable_label)->toBe($request->reference)
        ->and($request->refresh()->first_viewed_at)->not->toBeNull();
});

it('moves requests through the workflow and never trusts an invalid status', function () {
    $request = consultation();
    $manager = staffUser('manager');

    asAdmin($manager)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['status' => 'contacted'])->assertOk()
        ->assertJsonPath('data.status', 'contacted')
        ->assertJsonPath('data.assigned_to.id', $manager->id);
    expect($request->refresh()->contacted_at)->not->toBeNull();

    assertErrorCode(asAdmin($manager)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['status' => 'bogus']), 'VALIDATION_FAILED', 422);

    asAdmin($manager)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['status' => 'closed'])->assertOk();
    assertErrorCode(asAdmin($manager)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['status' => 'spam']), 'INVALID_STATE_TRANSITION', 409)
        ->assertJsonPath('errors.from', 'closed')
        ->assertJsonPath('errors.allowed', ['contacted', 'scheduled']);
});

it('lets only whole-inbox staff assign, and only to people who can see requests', function () {
    $staff = staffUser('staff');
    $request = consultation(assignedTo: $staff->id);
    $admin = staffUser('admin');
    $writer = staffUser('staff', ['is_active' => false]);

    assertErrorCode(asAdmin($staff)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['assigned_to' => null]), 'FORBIDDEN', 403);
    asAdmin($staff)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['status' => 'scheduled'])->assertOk();

    assertErrorCode(asAdmin($admin)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['assigned_to' => $writer->id]), 'VALIDATION_FAILED', 422)
        ->assertJsonPath('errors.assigned_to.0', 'This person cannot be assigned requests.');
    asAdmin($admin)->patchJson("/api/v1/admin/consultation-requests/{$request->id}", ['assigned_to' => $admin->id, 'follow_up_at' => now()->addDays(2)->toIso8601String()])
        ->assertOk()->assertJsonPath('data.assigned_to.id', $admin->id);

    expect(AuditLog::query()->where('action', 'consultation.assigned')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'consultation.follow_up_changed')->count())->toBe(1);
});

it('keeps notes encrypted and out of the audit log', function () {
    $request = consultation();
    $manager = staffUser('manager', ['name' => 'Cathy Mwangi']);

    asAdmin($manager)->postJson("/api/v1/admin/consultation-requests/{$request->id}/notes", ['body' => 'Left a voicemail at 10:15 AM.'])
        ->assertCreated()
        ->assertJsonPath('data.notes.0.body', 'Left a voicemail at 10:15 AM.')
        ->assertJsonPath('data.notes.0.author.name', 'Cathy Mwangi');
    assertErrorCode(asAdmin($manager)->postJson("/api/v1/admin/consultation-requests/{$request->id}/notes", ['body' => '']), 'VALIDATION_FAILED', 422);

    expect((string) DB::table('consultation_request_notes')->value('body'))->not->toContain('voicemail')
        ->and(json_encode(AuditLog::query()->where('action', 'consultation.note_added')->sole()->new_values))->not->toContain('voicemail');
});

it('deletes for good with the export permission only', function () {
    $request = consultation();
    (new ConsultationNote(['body' => 'Called.']))->forceFill(['consultation_request_id' => $request->id])->save();

    assertErrorCode(asAdmin(staffUser('manager'))->deleteJson("/api/v1/admin/consultation-requests/{$request->id}"), 'FORBIDDEN', 403);
    asAdmin(staffUser('admin'))->deleteJson("/api/v1/admin/consultation-requests/{$request->id}")->assertOk();

    expect(ConsultationRequest::query()->count())->toBe(0)
        ->and(ConsultationNote::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'consultation.deleted')->sole()->auditable_label)->toBe($request->reference);
});

it('applies bulk actions and skips requests that cannot move', function () {
    $new = consultation();
    $closed = consultation(status: ConsultationStatus::Closed);
    $manager = staffUser('manager');

    asAdmin($manager)->postJson('/api/v1/admin/consultation-requests/bulk', ['action' => 'spam', 'ids' => [$new->id, $closed->id]])
        ->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.skipped', 1);
    expect($new->refresh()->status)->toBe(ConsultationStatus::Spam);

    assertErrorCode(asAdmin(staffUser('staff'))->postJson('/api/v1/admin/consultation-requests/bulk', ['action' => 'assign', 'ids' => [$new->id], 'assigned_to' => $manager->id]), 'FORBIDDEN', 403);
});

it('exports a formula-safe CSV for admins and records the export', function () {
    consultation(['name' => '=HYPERLINK("x")', 'phone' => '(253) 555-0198']);

    assertErrorCode(asAdmin(staffUser('manager'))->get('/api/v1/admin/consultation-requests/export', ['Accept' => 'application/json']), 'FORBIDDEN', 403);

    $response = asAdmin(staffUser('admin'))->get('/api/v1/admin/consultation-requests/export')->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Reference,Received,Status')
        ->toContain("'=HYPERLINK")
        ->toContain('(253) 555-0198')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(AuditLog::query()->where('action', 'consultation.exported')->sole()->new_values['rows'])->toBe(1);
});

it('summarises the queue for the dashboard within the viewer\'s scope', function () {
    $staff = staffUser('staff');
    consultation(['topic' => 'insurance'], assignedTo: $staff->id);
    ConsultationRequest::factory()->status(ConsultationStatus::New)->receivedAt(now()->subDays(2))->create(['topic' => 'spravato']);
    consultation(['topic' => 'spravato'], ConsultationStatus::Closed);

    asAdmin(staffUser('manager'))->getJson('/api/v1/admin/consultation-requests/overview?range=7')->assertOk()
        ->assertJsonPath('data.queue.waiting', 2)
        ->assertJsonPath('data.queue.waiting_over_day', 1)
        ->assertJsonPath('data.queue.overdue', 1)
        ->assertJsonPath('data.queue.longest.0.is_overdue', true)
        ->assertJsonPath('data.new_requests.count', 3)
        ->assertJsonPath('data.topics.0.value', 'spravato')
        ->assertJsonPath('data.topics.0.percent', 67)
        ->assertJsonCount(7, 'data.daily')
        ->assertJsonCount(3, 'data.recent');

    asAdmin($staff)->getJson('/api/v1/admin/consultation-requests/overview')->assertOk()->assertJsonPath('data.queue.waiting', 1);
    asAdmin($staff)->getJson('/api/v1/admin/consultation-requests/waiting')->assertOk()->assertJsonPath('data.waiting', 1);
});

it('purges requests past the retention period and spam after 30 days', function () {
    $old = ConsultationRequest::factory()->receivedAt(now()->subDays(400))->create();
    $spam = ConsultationRequest::factory()->status(ConsultationStatus::Spam)->receivedAt(now()->subDays(31))->create();
    $recent = ConsultationRequest::factory()->receivedAt(now()->subDays(31))->create();

    $this->artisan('consultations:purge')->assertSuccessful()->expectsOutput('Deleted 2 requests.');

    expect(ConsultationRequest::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(AuditLog::query()->where('action', 'consultation.purged')->pluck('auditable_label')->sort()->values()->all())->toBe(collect([$old->reference, $spam->reference])->sort()->values()->all());
});
