<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Media\Models\Media;
use Uptms\Media\Models\MediaFolder;
use Uptms\Media\Rules\PlaceableMedia;
use Uptms\Media\Services\MediaUsageRecorder;

beforeEach(fn () => Storage::fake('public'));

/** A real JPEG with an EXIF block (camera make + a GPS-looking comment). */
function jpegWithExif(int $width = 2000, int $height = 1200): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 24, 60, 220));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();

    $payload = "Exif\0\0".'MM'."\0*\0\0\0\x08".'Canon EOS GPS 47.2271 -122.5418';
    $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    $path = tempnam(sys_get_temp_dir(), 'jpg');
    file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

    return new UploadedFile($path, 'Clinic Waiting Room.JPG', 'image/jpeg', null, true);
}

it('stores an upload re-encoded, without metadata, with responsive variants', function () {
    $response = asAdmin(staffUser('manager'))->post('/api/v1/admin/media', ['file' => jpegWithExif()], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'image')
        ->assertJsonPath('data.width', 2000)
        ->assertJsonPath('data.needs_alt_text', true)
        ->assertJsonPath('data.filename', 'Clinic Waiting Room.JPG');

    $media = Media::query()->findOrFail($response->json('data.id'));
    $stored = Storage::disk('public')->get($media->path);

    expect($media->path)->toEndWith('-clinic-waiting-room.jpg')
        ->and($stored)->not->toContain('Canon')
        ->and($stored)->not->toContain('47.2271')
        // Queue is sync in tests: variants exist for 480/960/1600/2000 in AVIF and WebP.
        ->and($media->variants()->count())->toBe(8)
        ->and($media->variants()->where('format', 'webp')->max('width'))->toBe(2000);

    foreach ($media->variants as $variant) {
        Storage::disk('public')->assertExists($variant->path);
    }
});

it('refuses files whose bytes are not an allowed type, whatever the name says', function () {
    $fake = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned";');

    asAdmin(staffUser('manager'))->post('/api/v1/admin/media', ['file' => $fake], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_FAILED')->assertJsonStructure(['errors' => ['file']]);

    expect(Media::query()->count())->toBe(0);
});

it('keeps staff without media.manage to browsing', function () {
    $staff = staffUser('staff');

    asAdmin($staff)->getJson('/api/v1/admin/media')->assertOk();
    assertErrorCode(asAdmin($staff)->post('/api/v1/admin/media', ['file' => jpegWithExif(10, 10)], ['Accept' => 'application/json']), 'FORBIDDEN', 403);

    $media = Media::factory()->create();
    assertErrorCode(asAdmin($staff)->putJson("/api/v1/admin/media/{$media->id}", ['alt' => 'x']), 'FORBIDDEN', 403);
    assertErrorCode(asAdmin($staff)->deleteJson("/api/v1/admin/media/{$media->id}"), 'FORBIDDEN', 403);
});

it('blocks trashing a file that is used on the site, then allows it once released', function () {
    $media = Media::factory()->create();
    $owner = MediaFolder::query()->create(['name' => 'Owner', 'slug' => 'owner']);
    $usages = app(MediaUsageRecorder::class);
    $usages->sync($owner, [['media_id' => $media->id, 'context' => 'Hero']], 'Home');
    $manager = staffUser('manager');

    assertErrorCode(asAdmin($manager)->deleteJson("/api/v1/admin/media/{$media->id}"), 'CONFLICT', 409)
        ->assertJsonPath('errors.in_use.0.usages', 1);
    asAdmin($manager)->getJson("/api/v1/admin/media/{$media->id}")->assertOk()
        ->assertJsonPath('data.usages.0.label', 'Home')->assertJsonPath('data.usage_state', 'in_use');

    $usages->clear($owner);
    expect($media->refresh()->last_used_at)->not->toBeNull();

    asAdmin($manager)->deleteJson("/api/v1/admin/media/{$media->id}")->assertOk();
    expect(Media::onlyTrashed()->count())->toBe(1);

    asAdmin($manager)->postJson("/api/v1/admin/media/{$media->id}/restore")->assertOk();
    expect(Media::query()->count())->toBe(1);
});

it('reports unused and missing-alt counts and filters by them', function () {
    $used = Media::factory()->create();
    Media::factory()->withoutAlt()->create();
    Media::factory()->document()->create();
    $owner = MediaFolder::query()->create(['name' => 'Owner', 'slug' => 'owner']);
    app(MediaUsageRecorder::class)->sync($owner, [['media_id' => $used->id, 'context' => 'Photo']], 'Team');

    asAdmin(staffUser('manager'))->getJson('/api/v1/admin/media?filter[show]=unused')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.counts.all', 3)
        ->assertJsonPath('meta.counts.in_use', 1)
        ->assertJsonPath('meta.counts.no_alt', 1)
        ->assertJsonPath('meta.unused.never_used', 2);
});

it('only lets images with alt text (or marked decorative) be placed', function () {
    $ok = Media::factory()->create();
    $missing = Media::factory()->withoutAlt()->create();
    $decorative = Media::factory()->withoutAlt()->create(['is_decorative' => true]);
    $pdf = Media::factory()->document()->create();

    $check = fn (mixed $id) => Validator::make(['image' => $id], ['image' => [new PlaceableMedia]])->passes();

    expect($check($ok->id))->toBeTrue()
        ->and($check($missing->id))->toBeFalse()
        ->and($check($decorative->id))->toBeTrue()
        ->and($check($pdf->id))->toBeFalse()
        ->and($check(999_999))->toBeFalse();
});

it('saves alt text, focal point and decorative flag, and audits the change', function () {
    $media = Media::factory()->withoutAlt()->create();

    asAdmin(staffUser('manager'))->putJson("/api/v1/admin/media/{$media->id}", [
        'alt' => 'Clinician adjusting a Deep TMS helmet', 'focal_x' => 62.5, 'focal_y' => 30,
    ])->assertOk()->assertJsonPath('data.needs_alt_text', false)->assertJsonPath('data.focal.x', 62.5);

    expect(AuditLog::query()->where('action', 'media.updated')->exists())->toBeTrue();
});

it('handles palette PNGs (8-bit colour) through the AVIF pipeline', function () {
    $image = imagecreate(600, 400);
    imagecolorallocate($image, 200, 225, 249);
    $path = tempnam(sys_get_temp_dir(), 'png');
    imagepng($image, $path);

    $id = asAdmin(staffUser('manager'))->post('/api/v1/admin/media', ['file' => new UploadedFile($path, 'logo.png', 'image/png', null, true)], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.id');

    expect(Media::query()->findOrFail($id)->variants()->where('format', 'avif')->count())->toBe(2);
});
