<?php

declare(strict_types=1);

namespace Uptms\Media\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Uptms\Media\Models\Media;

/**
 * File paths: media/{uuid}/{revision}-{name}.{ext}. The random revision
 * segment changes when a file is replaced, so browsers and CDNs never serve
 * the old image from cache.
 */
final class MediaStorage
{
    public function disk(?string $name = null): Filesystem
    {
        return Storage::disk($name ?? $this->diskName());
    }

    public function diskName(): string
    {
        $disk = config('media.disk');

        return is_string($disk) ? $disk : 'public';
    }

    public function originalPath(string $uuid, string $filename, string $extension): string
    {
        return sprintf('media/%s/%s-%s.%s', $uuid, Str::lower(Str::random(6)), $this->stem($filename), $extension);
    }

    public function variantPath(string $originalPath, int $width, string $format): string
    {
        return sprintf('%s/%s-%d.%s', dirname($originalPath), pathinfo($originalPath, PATHINFO_FILENAME), $width, $format);
    }

    /** Remove the original and every variant from disk. */
    public function deleteFiles(Media $media): void
    {
        $paths = $media->variants()->pluck('path')->push($media->path)->all();
        $this->disk($media->disk)->delete($paths);
    }

    /** `Clinic Waiting Room.JPG` → `clinic-waiting-room` */
    public function stem(string $filename): string
    {
        $stem = Str::slug(pathinfo($filename, PATHINFO_FILENAME));

        return $stem === '' ? 'file' : mb_substr($stem, 0, 80);
    }
}
