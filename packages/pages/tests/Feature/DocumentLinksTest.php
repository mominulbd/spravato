<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Uptms\Media\Models\Media;
use Uptms\Pages\Models\Page;

/*
 * Links to official PDFs carry the media id (a link field's `media_id`, or
 * a document field in a link row). The website gets {label, href} with
 * the file's current address, so replacing the PDF never breaks the link.
 */

function documentPdf(string $name = 'guide.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
}

/**
 * @param  array<string, mixed>  $primary
 * @param  list<array<string, mixed>>  $asideLinks
 */
function publishDocumentPage(array $primary, array $asideLinks): Page
{
    $manager = staffUser('manager');
    $id = asAdmin($manager)->postJson('/api/v1/admin/pages', ['title' => 'Documents', 'slug' => 'documents', 'template' => 'standard'])->assertCreated()->json('data.id');

    asAdmin($manager)->putJson("/api/v1/admin/pages/{$id}/content", ['lock_version' => 1, 'blocks' => [
        ['type' => 'hero', 'is_visible' => true, 'data' => ['headline' => 'Official documents', 'aside' => 'image', 'primary' => $primary]],
        ['type' => 'faq', 'is_visible' => true, 'data' => ['headline' => 'Questions', 'group' => 'home', 'aside_links' => $asideLinks]],
    ]])->assertOk();
    asAdmin($manager)->postJson("/api/v1/admin/pages/{$id}/publish")->assertOk();

    return Page::query()->findOrFail($id);
}

beforeEach(fn () => Storage::fake('public'));

it('serves document links at the file’s current address, also after the file is replaced', function () {
    $manager = staffUser('manager');
    $id = asAdmin($manager)->post('/api/v1/admin/media', ['file' => documentPdf()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    $before = parse_url(Media::query()->findOrFail($id)->url(), PHP_URL_PATH);

    $page = publishDocumentPage(
        ['label' => 'Read the Medication Guide →', 'href' => '', 'media_id' => $id],
        [['label' => 'Medication Guide →', 'media_id' => $id], ['label' => 'Call us', 'href' => 'tel:clinic']],
    );

    $this->getJson('/api/v1/public/page?path=/documents')->assertOk()
        ->assertJsonPath('data.blocks.0.data.primary', ['label' => 'Read the Medication Guide →', 'href' => $before])
        ->assertJsonPath('data.blocks.1.data.aside_links', [['href' => $before, 'label' => 'Medication Guide →'], ['href' => 'tel:clinic', 'label' => 'Call us']]);

    // The library knows the page uses it, so it can't be trashed from under the link.
    assertErrorCode(asAdmin($manager)->deleteJson("/api/v1/admin/media/{$id}"), 'CONFLICT', 409);

    asAdmin($manager)->post("/api/v1/admin/media/{$id}/replace", ['file' => documentPdf('guide-2026.pdf')], ['Accept' => 'application/json'])->assertOk();
    $after = parse_url(Media::query()->findOrFail($id)->url(), PHP_URL_PATH);

    expect($after)->not->toBe($before);
    $this->getJson('/api/v1/public/page?path=/documents')->assertOk()
        ->assertJsonPath('data.blocks.0.data.primary.href', $after)
        ->assertJsonPath('data.blocks.1.data.aside_links.0.href', $after);

    // The editor keeps the reference, not the address.
    asAdmin($manager)->getJson("/api/v1/admin/pages/{$page->id}")->assertOk()
        ->assertJsonPath('data.blocks.0.data.primary.media_id', $id)
        ->assertJsonPath('data.blocks.1.data.aside_links.0.media_id', $id);
});

it('drops a document link once its file is trashed', function () {
    $document = Media::factory()->document()->create();

    publishDocumentPage(
        ['label' => 'Read the Medication Guide →', 'media_id' => $document->id],
        [['label' => 'Medication Guide →', 'media_id' => $document->id], ['label' => 'Call us', 'href' => 'tel:clinic']],
    );

    $document->delete();

    $this->getJson('/api/v1/public/page?path=/documents')->assertOk()
        ->assertJsonPath('data.blocks.0.data.primary', ['label' => '', 'href' => ''])
        ->assertJsonPath('data.blocks.1.data.aside_links', [['href' => 'tel:clinic', 'label' => 'Call us']]);
});

it('only takes documents from the library as link targets', function () {
    $image = Media::factory()->create();
    $page = asAdmin(staffUser('manager'))->postJson('/api/v1/admin/pages', ['title' => 'Docs', 'slug' => 'docs', 'template' => 'standard'])->json('data.id');

    asAdmin(staffUser('staff'))->putJson("/api/v1/admin/pages/{$page}/content", ['lock_version' => 1, 'blocks' => [
        ['type' => 'hero', 'data' => ['headline' => 'Docs', 'aside' => 'image', 'primary' => ['label' => 'Guide', 'media_id' => $image->id]]],
        ['type' => 'faq', 'data' => ['headline' => 'Q', 'group' => 'home', 'aside_links' => [
            ['label' => 'Nowhere'],
            ['label' => 'Ghost', 'media_id' => 999_999],
            ['label' => 'Script', 'href' => 'javascript:alert(1)'],
        ]]],
    ]])->assertStatus(422)->assertJsonStructure(['errors' => [
        'blocks.0.data.primary.media_id',
        'blocks.1.data.aside_links.0',
        'blocks.1.data.aside_links.1.media_id',
        'blocks.1.data.aside_links.2.href',
    ]]);

    // A label with neither an address nor a document is refused.
    asAdmin(staffUser('staff'))->putJson("/api/v1/admin/pages/{$page}/content", ['lock_version' => 1, 'blocks' => [
        ['type' => 'hero', 'data' => ['headline' => 'Docs', 'aside' => 'image', 'primary' => ['label' => 'Guide', 'href' => '']]],
    ]])->assertStatus(422)->assertJsonStructure(['errors' => ['blocks.0.data.primary']]);
});

it('keeps publishing pages with document links to content.publish', function () {
    $document = Media::factory()->document()->create();
    $page = asAdmin(staffUser('manager'))->postJson('/api/v1/admin/pages', ['title' => 'Docs', 'slug' => 'docs', 'template' => 'standard'])->json('data.id');

    asAdmin(staffUser('staff'))->putJson("/api/v1/admin/pages/{$page}/content", ['lock_version' => 1, 'blocks' => [
        ['type' => 'hero', 'data' => ['headline' => 'Docs', 'aside' => 'image', 'primary' => ['label' => 'Guide', 'media_id' => $document->id]]],
    ]])->assertOk();

    assertErrorCode(asAdmin(staffUser('staff'))->postJson("/api/v1/admin/pages/{$page}/publish"), 'FORBIDDEN', 403);
    $this->getJson('/api/v1/public/page?path=/docs')->assertNotFound();
});
