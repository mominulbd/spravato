<?php

declare(strict_types=1);

namespace Database\Seeders\Content;

use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Uptms\Media\Actions\UploadMediaAction;
use Uptms\Media\Jobs\GenerateMediaVariants;
use Uptms\Media\Models\Media;
use Uptms\Media\Models\MediaFolder;

/**
 * Uploads the Figma photography and logos (database/seeders/assets/site)
 * through the real pipeline, so seeded images are re-encoded, get variants
 * and carry alt text like any upload. Idempotent by original file name.
 */
class SiteMediaSeeder extends Seeder
{
    /** file → [folder, alt text] */
    private const array FILES = [
        'tms-clinic-care.jpg' => ['Clinic photos', 'Clinician preparing a smiling patient for a Deep TMS session'],
        'tms-treatment-session.jpg' => ['Clinic photos', 'Patient seated under a TMS treatment helmet while a clinician looks on'],
        'deep-tms-depression.png' => ['Clinic photos', 'Person sitting by a bright window, looking out thoughtfully'],
        'tms-anxious-depression.png' => ['Clinic photos', 'Person at a desk with a laptop, pinching the bridge of their nose'],
        'late-life-depression.png' => ['Clinic photos', 'Older woman with her eyes closed and hands clasped in quiet reflection'],
        'deep-tms-ocd.png' => ['Clinic photos', 'Young man in rubber gloves scrubbing a table'],
        'depression-consultation.jpg' => ['Clinic photos', 'Patient on a sofa talking with a clinician who takes notes'],
        'deep-tms-explained.jpg' => ['Clinic photos', 'Young woman with her hand at her chin, gazing out of a window'],
        'ocd-understanding.jpg' => ['Clinic photos', 'One woman comforting another who covers her face'],
        'ayub-mwaura.jpg' => ['Team', 'Ayub Mwaura in a white coat'],
        'joy-kiongo.jpg' => ['Team', 'Joy Kiongo in a black blazer'],
        'cathy-mwangi.jpg' => ['Team', 'Cathy Mwangi seated in a denim jacket'],
        'insurance-cigna.jpg' => ['Insurance logos', 'Cigna'],
        'insurance-coordinated-care.jpg' => ['Insurance logos', 'Coordinated Care'],
        'insurance-chpw.jpg' => ['Insurance logos', 'Community Health Plan of Washington'],
        'insurance-molina.jpg' => ['Insurance logos', 'Molina Healthcare'],
        'insurance-premera.jpg' => ['Insurance logos', 'Premera Blue Cross'],
        'insurance-tricare.jpg' => ['Insurance logos', 'TRICARE'],
        'insurance-regence.jpg' => ['Insurance logos', 'Regence BlueShield'],
        'insurance-providerone.jpg' => ['Insurance logos', 'ProviderOne'],
        'insurance-lni.jpg' => ['Insurance logos', 'Washington State Department of Labor & Industries'],
        'insurance-medicaid.jpg' => ['Insurance logos', 'Medicaid'],
    ];

    public function __construct(private readonly UploadMediaAction $upload) {}

    public function run(): void
    {
        foreach (['Clinic photos', 'Team', 'Insurance logos', 'Share images', 'Documents'] as $order => $name) {
            MediaFolder::query()->firstOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name, 'sort_order' => $order + 1]);
        }

        foreach (self::FILES as $file => [$folder, $alt]) {
            $existing = Media::query()->where('original_name', $file)->first();

            if ($existing instanceof Media) {
                $this->repair($existing, $alt);

                continue;
            }

            $path = database_path('seeders/assets/site/'.$file);
            $media = $this->upload->execute(new UploadedFile($path, $file, null, null, true), MediaFolder::query()->where('name', $folder)->value('id'), null);
            $media->forceFill(['alt' => $alt])->saveQuietly();
        }
    }

    /** A run that stopped half-way can leave a file without alt text or variants. */
    private function repair(Media $media, string $alt): void
    {
        if ($media->alt === null || $media->alt === '') {
            $media->forceFill(['alt' => $alt])->saveQuietly();
        }

        if ($media->isImage() && ! $media->variants()->exists()) {
            GenerateMediaVariants::dispatchSync($media->id, $media->path);
        }
    }

    public static function id(string $file): int
    {
        return (int) Media::query()->where('original_name', $file)->value('id');
    }
}
