<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Uptms\Media\Models\Media;
use Uptms\Menus\Models\Menu;

/*
 * Official documents in a menu link to the media item, not to its file
 * address: replacing the PDF gives it a new address and deletes the old
 * file, so a stored URL would 404.
 */

function menuDocumentPdf(string $name = 'guide.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
}

beforeEach(function () {
    Storage::fake('public');
    Menu::query()->create(['key' => 'documents', 'name' => 'Official documents', 'sort_order' => 1]);
});

it('serves a document item at the file’s current address, also after the file is replaced', function () {
    $manager = staffUser('manager');
    $id = asAdmin($manager)->post('/api/v1/admin/media', ['file' => menuDocumentPdf()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    $before = parse_url(Media::query()->findOrFail($id)->url(), PHP_URL_PATH);

    asAdmin($manager)->putJson('/api/v1/admin/menus/documents', ['items' => [
        ['label' => 'Medication Guide', 'type' => 'document', 'media_id' => $id],
    ]])->assertOk()
        ->assertJsonPath('data.items.0.type', 'document')
        ->assertJsonPath('data.items.0.media_id', $id)
        ->assertJsonPath('data.items.0.document_filename', Media::query()->findOrFail($id)->filename)
        ->assertJsonPath('data.preview.0.href', $before);

    // The website's shape is unchanged: {label, href}.
    $this->getJson('/api/v1/public/menus')->assertOk()
        ->assertJsonPath('data.documents', [['label' => 'Medication Guide', 'href' => $before, 'children' => []]]);

    // Placed in a menu counts as used: the library refuses to trash it.
    assertErrorCode(asAdmin($manager)->deleteJson("/api/v1/admin/media/{$id}"), 'CONFLICT', 409);

    asAdmin($manager)->post("/api/v1/admin/media/{$id}/replace", ['file' => menuDocumentPdf('guide-2026.pdf')], ['Accept' => 'application/json'])->assertOk();
    $after = parse_url(Media::query()->findOrFail($id)->url(), PHP_URL_PATH);

    expect($after)->not->toBe($before);
    $this->getJson('/api/v1/public/menus')->assertOk()->assertJsonPath('data.documents.0.href', $after);
});

it('drops a document item whose file is trashed or purged', function () {
    $document = Media::factory()->document()->create();

    asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/documents', ['items' => [
        ['label' => 'Medication Guide', 'type' => 'document', 'media_id' => $document->id],
        ['label' => 'Call', 'type' => 'link', 'url' => 'tel:+12532344199'],
    ]])->assertOk();

    $document->delete();

    $this->getJson('/api/v1/public/menus')->assertOk()
        ->assertJsonCount(1, 'data.documents')
        ->assertJsonPath('data.documents.0.label', 'Call');
    asAdmin(staffUser('manager'))->getJson('/api/v1/admin/menus/documents')->assertOk()
        ->assertJsonPath('data.items.0.media_id', $document->id)
        ->assertJsonPath('data.items.0.document_filename', null)
        ->assertJsonPath('data.items.0.href', null);

    $document->forceDelete();
    $this->getJson('/api/v1/public/menus')->assertOk()->assertJsonCount(1, 'data.documents');
});

it('only takes documents from the library, and only from staff who may publish', function () {
    $image = Media::factory()->create();
    $document = Media::factory()->document()->create();

    assertErrorCode(asAdmin(staffUser('staff'))->putJson('/api/v1/admin/menus/documents', ['items' => [
        ['label' => 'Medication Guide', 'type' => 'document', 'media_id' => $document->id],
    ]]), 'FORBIDDEN', 403);

    asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/documents', ['items' => [
        ['label' => 'A photo', 'type' => 'document', 'media_id' => $image->id],
        ['label' => 'Nothing', 'type' => 'document'],
        ['label' => 'Ghost', 'type' => 'document', 'media_id' => 999_999],
    ]])->assertStatus(422)->assertJsonStructure(['errors' => ['items.0.media_id', 'items.1.media_id', 'items.2.media_id']]);

    expect(Menu::query()->where('key', 'documents')->firstOrFail()->items()->count())->toBe(0);
});
