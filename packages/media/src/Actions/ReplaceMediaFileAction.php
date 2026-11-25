<?php

declare(strict_types=1);

namespace Uptms\Media\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Media\Jobs\GenerateMediaVariants;
use Uptms\Media\Models\Media;
use Uptms\Media\Services\MediaStorage;
use Uptms\Publishing\Events\ContentPublished;

/**
 * "Replacing keeps every page linked to the new file" (Figma 18): the id,
 * alt text and usages stay; the file and its variants change.
 */
final readonly class ReplaceMediaFileAction
{
    public function __construct(
        private StoreUploadedFile $files,
        private MediaStorage $storage,
        private AuditRecorder $audit,
    ) {}

    public function execute(Media $media, UploadedFile $file): Media
    {
        $stored = $this->files->store($file, $media->uuid, $media->filename);

        if ($stored['kind'] !== $media->kind) {
            $this->storage->disk($stored['disk'])->delete($stored['path']);

            throw new GenericDomainException(ErrorCode::ValidationFailed, 'Replace an image with an image, or a document with a document.', [
                'file' => ['Replace an image with an image, or a document with a document.'],
            ]);
        }

        $old = $media->replicate();
        $old->setRelation('variants', $media->variants()->get());

        DB::transaction(function () use ($media, $stored, $file): void {
            $media->forceFill([...$stored, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 190)])->saveQuietly();
            $media->variants()->delete();
            $this->audit->record('media.replaced', $media, null, ['original_name' => $media->original_name, 'size' => $media->size]);
        });

        DB::afterCommit(function () use ($old): void {
            $this->storage->disk($old->disk)->delete([$old->path, ...$old->variants->pluck('path')->all()]);
        });

        if ($media->isImage()) {
            GenerateMediaVariants::dispatch($media->id, $media->path);
        } elseif ($media->usages()->exists()) {
            ContentPublished::dispatch(['content']);
        }

        return $media->refresh();
    }
}
