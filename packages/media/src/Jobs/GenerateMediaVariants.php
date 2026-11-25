<?php

declare(strict_types=1);

namespace Uptms\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Uptms\Media\Models\Media;
use Uptms\Media\Services\ImageProcessor;
use Uptms\Media\Services\MediaStorage;
use Uptms\Publishing\Events\ContentPublished;

/**
 * Builds the responsive WebP/AVIF set for one image. Queued because AVIF
 * encoding takes a few seconds; until it finishes the site serves the
 * re-encoded original.
 */
final class GenerateMediaVariants implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly int $mediaId, public readonly string $path)
    {
        $this->afterCommit();
    }

    public function handle(ImageProcessor $images, MediaStorage $storage): void
    {
        $media = Media::query()->find($this->mediaId);

        // Replaced again (or deleted) since this job was queued.
        if (! $media instanceof Media || ! $media->isImage() || $media->path !== $this->path) {
            return;
        }

        $disk = $storage->disk($media->disk);

        // `throw => false` on the disks turns a missing original into null,
        // and an empty string here would write variants of nothing. Fail so
        // the job retries and the failure is visible.
        $binary = $disk->get($media->path);

        if ($binary === null || $binary === '') {
            throw new RuntimeException(sprintf('Could not read %s from the [%s] disk.', $media->path, $media->disk));
        }

        /** @var list<int> $configured */
        $configured = config('media.widths');
        /** @var list<string> $formats */
        $formats = config('media.formats');

        $widths = array_values(array_filter($configured, fn (int $width): bool => $width < (int) $media->width));
        $widths[] = (int) $media->width;

        $rows = [];

        foreach ($formats as $format) {
            foreach (array_unique($widths) as $width) {
                $variant = $images->variant($binary, $width, $format);
                $path = $storage->variantPath($media->path, $variant['width'], $format);

                if ($disk->put($path, $variant['binary'], ['visibility' => 'public']) === false) {
                    throw new RuntimeException(sprintf('Could not write %s to the [%s] disk.', $path, $media->disk));
                }
                $rows[] = [
                    'format' => $format,
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                    'path' => $path,
                    'size' => strlen($variant['binary']),
                ];
            }
        }

        DB::transaction(function () use ($media, $rows): void {
            $media->variants()->delete();
            $media->variants()->createMany($rows);
        });

        if ($media->usages()->exists()) {
            ContentPublished::dispatch(['content']);
        }
    }
}
