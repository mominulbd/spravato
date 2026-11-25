<?php

declare(strict_types=1);

namespace Uptms\Media\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Uptms\Media\Jobs\GenerateMediaVariants;
use Uptms\Media\Models\Media;

final readonly class UploadMediaAction
{
    public function __construct(private StoreUploadedFile $files) {}

    public function execute(UploadedFile $file, ?int $folderId, ?int $actorId): Media
    {
        $uuid = (string) Str::uuid7();
        $name = mb_substr($file->getClientOriginalName(), 0, 190);
        $stored = $this->files->store($file, $uuid, $name);

        $media = DB::transaction(function () use ($uuid, $name, $stored, $folderId, $actorId): Media {
            $media = new Media;
            $media->forceFill([
                'uuid' => $uuid,
                'folder_id' => $folderId,
                'filename' => $name,
                'original_name' => $name,
                'uploaded_by' => $actorId,
                ...$stored,
            ])->save();

            return $media;
        });

        if ($media->isImage()) {
            GenerateMediaVariants::dispatch($media->id, $media->path);
        }

        return $media;
    }
}
