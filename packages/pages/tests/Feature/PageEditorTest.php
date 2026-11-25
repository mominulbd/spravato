<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Uptms\Media\Models\Media;
use Uptms\Pages\Models\Page;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Seo\Models\SeoMeta;

function newPage(string $slug = 'insurance', string $template = 'standard'): Page
{
    $id = asAdmin(staffUser('manager'))->postJson('/api/v1/admin/pages', [
        'title' => 'Insurance & coverage', 'slug' => $slug, 'template' => $template,
    ])->assertCreated()->json('data.id');

    return Page::query()->findOrFail($id);
}

/** @return array<string, mixed> */
function heroBlock(array $data = []): array
{
    return ['type' => 'hero', 'is_visible' => true, 'data' => [
        'eyebrow' => 'Deep TMS therapy in University Place, WA',
        'headline' => 'When depression won’t lift, *explore a new way forward.*',
        'aside' => 'image',
        'primary' => ['label' => 'Take the Free TMS Self-Check', 'href' => '/quiz'],
        ...$data,
    ]];
}

it('creates a draft with the template’s starter sections', function () {
    $page = newPage();

    expect($page->path)->toBe('/insurance')
        ->and($page->status->value)->toBe('draft')
        ->and($page->blocks()->pluck('type')->all())->toBe(['hero', 'rich_text', 'cta_band']);

    $this->getJson('/api/v1/public/page?path=/insurance')->assertNotFound();
});

it('refuses reserved and duplicate addresses', function () {
    newPage();
    $manager = staffUser('manager');

    asAdmin($manager)->postJson('/api/v1/admin/pages', ['title' => 'X', 'slug' => 'insurance', 'template' => 'standard'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['slug']]);
    asAdmin($manager)->postJson('/api/v1/admin/pages', ['title' => 'X', 'slug' => 'conditions', 'template' => 'standard'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['slug']]);
});

it('autosaves the working copy without touching the live page, guarded by lock_version', function () {
    $page = newPage();
    $editor = staffUser('staff');

    $response = asAdmin($editor)->putJson("/api/v1/admin/pages/{$page->id}/content", [
        'lock_version' => $page->lock_version, 'blocks' => [heroBlock()],
    ])->assertOk()->assertJsonPath('data.blocks.0.data.headline', 'When depression won’t lift, *explore a new way forward.*');

    expect($response->json('data.lock_version'))->toBe($page->lock_version + 1)
        ->and($response->json('data.blocks.0.data.card_title'))->toBe('');

    assertErrorCode(asAdmin($editor)->putJson("/api/v1/admin/pages/{$page->id}/content", [
        'lock_version' => $page->lock_version, 'blocks' => [heroBlock()],
    ]), 'CONFLICT', 409);
});

it('validates block data by its type', function () {
    $page = newPage();

    asAdmin(staffUser('staff'))->putJson("/api/v1/admin/pages/{$page->id}/content", [
        'lock_version' => $page->lock_version,
        'blocks' => [
            heroBlock(['headline' => str_repeat('x', 81), 'primary' => ['label' => 'Go', 'href' => 'javascript:alert(1)']]),
            ['type' => 'nope', 'data' => []],
        ],
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['blocks.0.data.headline', 'blocks.0.data.primary.href', 'blocks.1.type']]);
});

it('publishes with content.publish only, then serves the published snapshot', function () {
    Event::fake([ContentPublished::class]);
    $page = newPage();
    $page = Page::query()->findOrFail($page->id);

    asAdmin(staffUser('staff'))->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 1, 'blocks' => [heroBlock()]])->assertOk();
    assertErrorCode(asAdmin(staffUser('staff'))->postJson("/api/v1/admin/pages/{$page->id}/publish"), 'FORBIDDEN', 403);

    asAdmin(staffUser('manager'))->postJson("/api/v1/admin/pages/{$page->id}/publish")
        ->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.has_unpublished_changes', false);
    Event::assertDispatched(ContentPublished::class, fn (ContentPublished $event) => in_array('page:insurance', $event->tags, true));

    // A later draft edit doesn't reach the site until published again.
    asAdmin(staffUser('staff'))->putJson("/api/v1/admin/pages/{$page->id}/content", [
        'lock_version' => 2, 'blocks' => [heroBlock(['headline' => 'Changed in draft'])],
    ])->assertOk()->assertJsonPath('data.has_unpublished_changes', true);

    $this->getJson('/api/v1/public/page?path=/insurance')->assertOk()
        ->assertJsonPath('data.blocks.0.type', 'hero')
        ->assertJsonPath('data.blocks.0.data.headline', 'When depression won’t lift, *explore a new way forward.*');
});

it('numbers section rails in order and hides invisible blocks', function () {
    $page = newPage();
    $rich = fn (string $rail, bool $visible = true): array => ['type' => 'rich_text', 'is_visible' => $visible, 'data' => ['rail' => $rail, 'body' => 'Text']];

    asAdmin(staffUser('manager'))->putJson("/api/v1/admin/pages/{$page->id}/content", [
        'lock_version' => 1, 'blocks' => [heroBlock(), $rich('First'), $rich('Hidden', false), $rich('Second')],
    ])->assertOk();
    asAdmin(staffUser('manager'))->postJson("/api/v1/admin/pages/{$page->id}/publish")->assertOk();

    $this->getJson('/api/v1/public/page?path=/insurance')->assertOk()
        ->assertJsonCount(3, 'data.blocks')
        ->assertJsonPath('data.blocks.0.number', null)
        ->assertJsonPath('data.blocks.1.number', '01')
        ->assertJsonPath('data.blocks.2.number', '02');
});

it('presents images and records them as used, requiring alt text', function () {
    $page = newPage();
    $image = Media::factory()->create();
    $noAlt = Media::factory()->withoutAlt()->create();
    $manager = staffUser('manager');

    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 1, 'blocks' => [heroBlock(['image' => $noAlt->id])]])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['blocks.0.data.image']]);

    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 1, 'blocks' => [heroBlock(['image' => $image->id])]])->assertOk();
    asAdmin($manager)->postJson("/api/v1/admin/pages/{$page->id}/publish")->assertOk();

    expect($image->usages()->first()?->context)->toBe('Hero · Photo');
    $this->getJson('/api/v1/public/page?path=/insurance')->assertJsonPath('data.blocks.0.data.image.id', $image->id)
        ->assertJsonPath('data.blocks.0.data.image.alt', $image->alt);

    // Removing it from the draft keeps it in use while the live page shows it.
    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 2, 'blocks' => [heroBlock()]])->assertOk();
    expect($image->usages()->count())->toBe(1);
});

it('schedules a frozen version and publishes it when due', function () {
    $page = newPage();
    $manager = staffUser('manager');
    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 1, 'blocks' => [heroBlock(['headline' => 'Scheduled version'])]])->assertOk();

    asAdmin($manager)->postJson("/api/v1/admin/pages/{$page->id}/schedule", ['at' => now()->addHour()->toIso8601String()])
        ->assertOk()->assertJsonPath('data.status', 'scheduled');
    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 2, 'blocks' => [heroBlock(['headline' => 'Later edit'])]])->assertOk();

    $this->travel(2)->hours();
    $this->artisan('pages:publish-scheduled')->assertSuccessful();

    $this->getJson('/api/v1/public/page?path=/insurance')->assertOk()->assertJsonPath('data.blocks.0.data.headline', 'Scheduled version');
});

it('restores a revision into the draft', function () {
    $page = newPage();
    $manager = staffUser('manager');
    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 1, 'blocks' => [heroBlock(['headline' => 'Version A'])], 'checkpoint' => true])->assertOk();
    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 2, 'blocks' => [heroBlock(['headline' => 'Version B'])]])->assertOk();

    $revision = asAdmin($manager)->getJson("/api/v1/admin/pages/{$page->id}/revisions")->assertOk()->json('data.0.id');

    asAdmin($manager)->postJson("/api/v1/admin/pages/{$page->id}/revisions/{$revision}/restore", ['lock_version' => 3])
        ->assertOk()->assertJsonPath('data.blocks.0.data.headline', 'Version A');
});

it('serves a working-copy preview only with a valid token', function () {
    $page = newPage();
    $manager = staffUser('manager');
    $token = asAdmin($manager)->postJson("/api/v1/admin/pages/{$page->id}/preview")->assertOk()->json('data.token');

    $this->getJson('/api/v1/public/page?path=/insurance&preview='.urlencode($token))->assertOk()->assertJsonPath('data.preview', true);
    $this->getJson('/api/v1/public/page?path=/insurance&preview=forged.token')->assertNotFound();

    $this->travel(31)->minutes();
    $this->getJson('/api/v1/public/page?path=/insurance&preview='.urlencode($token))->assertNotFound();
});

it('keeps moving or deleting a live page to content.publish', function () {
    $page = newPage();
    asAdmin(staffUser('manager'))->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => 1, 'blocks' => [heroBlock()]])->assertOk();
    asAdmin(staffUser('manager'))->postJson("/api/v1/admin/pages/{$page->id}/publish")->assertOk();
    $staff = staffUser('staff');

    assertErrorCode(asAdmin($staff)->putJson("/api/v1/admin/pages/{$page->id}", ['title' => 'X', 'slug' => 'moved', 'template' => 'standard']), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($staff)->deleteJson("/api/v1/admin/pages/{$page->id}"), 'FORBIDDEN', 403);

    asAdmin(staffUser('manager'))->deleteJson("/api/v1/admin/pages/{$page->id}")->assertOk();
    $this->getJson('/api/v1/public/page?path=/insurance')->assertNotFound();
});

it('lists pages with tab counts', function () {
    newPage();
    newPage('privacy', 'legal');

    asAdmin(staffUser('staff'))->getJson('/api/v1/admin/pages?filter[template]=legal')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.path', '/privacy')
        ->assertJsonPath('meta.counts.draft', 1);
    asAdmin(staffUser('staff'))->getJson('/api/v1/admin/pages/block-types')->assertOk()->assertJsonPath('data.0.key', 'hero');
});

it('scores pages for the list once and again on "Save draft", never in Trash', function () {
    $page = newPage();
    $staff = staffUser('staff');

    $score = asAdmin($staff)->getJson('/api/v1/admin/pages')->assertOk()->json('data.0.seo_score');
    expect($score)->toBeInt()->toBeBetween(0, 100)
        ->and(SeoMeta::query()->where('seoable_type', 'page')->where('seoable_id', $page->id)->value('seo_score'))->toBe($score);

    SeoMeta::query()->where('seoable_type', 'page')->update(['analyzed_at' => null]);
    asAdmin($staff)->putJson("/api/v1/admin/pages/{$page->id}/content", ['lock_version' => $page->lock_version, 'blocks' => [heroBlock()], 'checkpoint' => true])->assertOk();
    expect(SeoMeta::query()->where('seoable_type', 'page')->where('seoable_id', $page->id)->value('analyzed_at'))->not->toBeNull();

    asAdmin(staffUser('manager'))->deleteJson("/api/v1/admin/pages/{$page->id}")->assertOk();
    asAdmin($staff)->getJson('/api/v1/admin/pages?filter[status]=trash')->assertOk()->assertJsonPath('data.0.seo_score', null);
});

it('saves incomplete drafts but refuses to publish or schedule them', function () {
    $page = newPage();
    $manager = staffUser('manager');

    // The Standard template starts with an empty rich-text body and CTA headline.
    asAdmin($manager)->putJson("/api/v1/admin/pages/{$page->id}/content", [
        'lock_version' => 1, 'blocks' => [heroBlock(['headline' => '']), ['type' => 'rich_text', 'data' => ['body' => '']]],
    ])->assertOk();

    asAdmin($manager)->postJson("/api/v1/admin/pages/{$page->id}/publish")
        ->assertStatus(422)->assertJsonStructure(['errors' => ['blocks.0.data.headline', 'blocks.1.data.body']]);
    asAdmin($manager)->postJson("/api/v1/admin/pages/{$page->id}/schedule", ['at' => now()->addDay()->toIso8601String()])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['blocks.1.data.body']]);
});
