<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Collections\Models\Faq;
use Uptms\Collections\Models\TeamMember;
use Uptms\Media\Models\Media;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Publishing\Services\BlockSources;

function faq(array $attributes = []): Faq
{
    $published = $attributes['is_published'] ?? true;
    unset($attributes['is_published']);
    $faq = new Faq(['group' => 'home', 'question' => 'Does TMS hurt?', 'answer' => 'Most people describe a tapping sensation.', 'sort_order' => 1, ...$attributes]);
    $faq->is_published = $published;
    $faq->save();

    return $faq;
}

it('lists collection tabs with counts and FAQ groups', function () {
    faq();
    faq(['group' => 'tms', 'question' => 'Q2']);

    asAdmin(staffUser('staff'))->getJson('/api/v1/admin/collections')->assertOk()
        ->assertJsonPath('data.0.key', 'team')
        ->assertJsonPath('data.1.key', 'faqs')
        ->assertJsonPath('data.1.count', 2)
        ->assertJsonPath('data.1.groups.0.value', 'home')
        ->assertJsonPath('data.1.groups.1.value', 'tms');
});

it('lets staff draft hidden items but not publish or edit what the site shows', function () {
    $staff = staffUser('staff');

    $id = asAdmin($staff)->postJson('/api/v1/admin/collections/faqs', [
        'group' => 'home', 'question' => 'Do I need a referral?', 'answer' => 'No.',
    ])->assertCreated()->assertJsonPath('data.is_published', false)->json('data.id');

    assertErrorCode(asAdmin($staff)->putJson("/api/v1/admin/collections/faqs/{$id}", [
        'group' => 'home', 'question' => 'Do I need a referral?', 'answer' => 'No.', 'is_published' => true,
    ]), 'FORBIDDEN', 403);

    $live = faq();
    assertErrorCode(asAdmin($staff)->putJson("/api/v1/admin/collections/faqs/{$live->id}", [
        'group' => 'home', 'question' => 'Changed', 'answer' => 'Changed',
    ]), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($staff)->deleteJson("/api/v1/admin/collections/faqs/{$live->id}"), 'FORBIDDEN', 403);
});

it('publishes with content.publish, audits, and revalidates the site', function () {
    Event::fake([ContentPublished::class]);
    $item = faq(['is_published' => false]);

    asAdmin(staffUser('manager'))->putJson("/api/v1/admin/collections/faqs/{$item->id}", [
        'group' => 'home', 'question' => 'Does TMS hurt?', 'answer' => 'Updated answer.', 'is_published' => true,
    ])->assertOk()->assertJsonPath('data.is_published', true);

    Event::assertDispatched(ContentPublished::class);
    expect(AuditLog::query()->where('action', 'faq.updated')->exists())->toBeTrue();
});

it('serves only published items publicly, filtered and ordered', function () {
    faq(['question' => 'Second', 'sort_order' => 2]);
    faq(['question' => 'First', 'sort_order' => 1]);
    faq(['question' => 'Hidden', 'is_published' => false]);
    faq(['question' => 'Other page', 'group' => 'tms']);

    $this->getJson('/api/v1/public/collections/faqs?group=home')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.question', 'First')
        ->assertJsonMissing(['question' => 'Hidden']);
    $this->getJson('/api/v1/public/collections/nope')->assertNotFound();

    expect(app(BlockSources::class)->resolve('faqs', ['group' => 'tms']))->toHaveCount(1);
});

it('records a team photo as a media usage and requires alt text', function () {
    $photo = Media::factory()->create();
    $noAlt = Media::factory()->withoutAlt()->create();
    $manager = staffUser('manager');
    $payload = ['name' => 'Ayub Mwaura', 'credentials' => 'MSN, PMHNP-BC', 'role_title' => 'Founder', 'schema_type' => 'Physician', 'show_on' => ['home', 'tms'], 'is_published' => true];

    asAdmin($manager)->postJson('/api/v1/admin/collections/team', [...$payload, 'photo_id' => $noAlt->id])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['photo_id']]);

    $id = asAdmin($manager)->postJson('/api/v1/admin/collections/team', [...$payload, 'photo_id' => $photo->id])
        ->assertCreated()->assertJsonPath('data.initials', 'AM')->assertJsonPath('data.photo.id', $photo->id)->json('data.id');

    expect($photo->usages()->first()?->label)->toBe('Team · Ayub Mwaura');
    $this->getJson('/api/v1/public/collections/team?page=tms')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/public/collections/team?page=quiz')->assertJsonCount(0, 'data');

    asAdmin($manager)->deleteJson("/api/v1/admin/collections/team/{$id}")->assertOk();
    expect($photo->usages()->count())->toBe(0)->and(TeamMember::withTrashed()->count())->toBe(1);
});

it('reorders a FAQ group only with the complete list', function () {
    $a = faq(['question' => 'A', 'sort_order' => 1]);
    $b = faq(['question' => 'B', 'sort_order' => 2]);
    faq(['question' => 'C', 'group' => 'tms']);
    $manager = staffUser('manager');

    asAdmin($manager)->putJson('/api/v1/admin/collections/faqs/reorder', ['group' => 'home', 'ids' => [$b->id, $a->id]])->assertOk();
    expect($b->refresh()->sort_order)->toBe(1);

    assertErrorCode(asAdmin($manager)->putJson('/api/v1/admin/collections/faqs/reorder', ['group' => 'home', 'ids' => [$a->id]]), 'CONFLICT', 409);
    assertErrorCode(asAdmin(staffUser('staff'))->putJson('/api/v1/admin/collections/faqs/reorder', ['group' => 'home', 'ids' => [$a->id, $b->id]]), 'FORBIDDEN', 403);
});
