<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Uptms\Media\Models\Media;
use Uptms\Media\Services\MediaPresenter;

/*
 * The official SPRAVATO® PDFs (docs/DEVELOPMENT-PLAN.md Phase 3): uploaded
 * as-is, typed by their bytes, and given a /storage address that the
 * deploy/ configs serve inline as application/pdf under a readable name.
 */

beforeEach(fn () => Storage::fake('public'));

function officialPdf(string $name = 'spravato-medication-guide.pdf'): UploadedFile
{
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    return UploadedFile::fake()->createWithContent($name, $pdf);
}

it('stores an uploaded PDF unchanged at a /storage address the proxy serves inline', function () {
    $file = officialPdf();
    $bytes = (string) file_get_contents((string) $file->getRealPath());

    $response = asAdmin(staffUser('manager'))->post('/api/v1/admin/media', ['file' => $file], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'document')
        ->assertJsonPath('data.mime', 'application/pdf')
        ->assertJsonPath('data.needs_alt_text', false)
        ->assertJsonPath('data.filename', 'spravato-medication-guide.pdf');

    $media = Media::query()->findOrFail($response->json('data.id'));
    $path = (string) parse_url((string) $response->json('data.url'), PHP_URL_PATH);

    // The shape deploy/nginx and deploy/apache match to add
    // `Content-Disposition: inline; filename="spravato-medication-guide.pdf"`.
    expect($path)->toMatch('#^/storage/media/[^/]+/[a-z0-9]{6}-(?<name>[a-z0-9-]+\.pdf)$#')
        ->and(basename($path))->toEndWith('-spravato-medication-guide.pdf')
        ->and(Storage::disk('public')->get($media->path))->toBe($bytes)
        ->and($media->variants()->count())->toBe(0);

    expect(app(MediaPresenter::class)->presentDocument($media->id))->toBe([
        'id' => $media->id,
        'url' => $media->url(),
        'mime' => 'application/pdf',
        'size' => strlen($bytes),
        'filename' => 'spravato-medication-guide.pdf',
    ]);
});

it('keeps staff without media.manage from uploading documents', function () {
    assertErrorCode(
        asAdmin(staffUser('staff'))->post('/api/v1/admin/media', ['file' => officialPdf()], ['Accept' => 'application/json']),
        'FORBIDDEN',
        403,
    );

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses a file that only looks like a PDF', function (string $content) {
    $fake = UploadedFile::fake()->createWithContent('spravato-medication-guide.pdf', $content);

    asAdmin(staffUser('manager'))->post('/api/v1/admin/media', ['file' => $fake], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED')->assertJsonStructure(['errors' => ['file']]);

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'html named .pdf' => '<html><body><script>alert(1)</script></body></html>',
    // finfo reports application/pdf for this one; the header check does not.
    'text before a PDF header' => str_repeat('A', 100)."%PDF-1.4\n%%EOF\n",
]);

it('presents only live documents as documents', function () {
    $image = Media::factory()->create();
    $trashed = Media::factory()->document()->create();
    $trashed->delete();

    $presenter = app(MediaPresenter::class);

    expect($presenter->presentDocument($image->id))->toBeNull()
        ->and($presenter->presentDocument($trashed->id))->toBeNull()
        ->and($presenter->presentDocument(null))->toBeNull();
});
