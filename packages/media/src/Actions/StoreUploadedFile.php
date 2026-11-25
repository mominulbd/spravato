<?php

declare(strict_types=1);

namespace Uptms\Media\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Uptms\Media\Models\Media;
use Uptms\Media\Services\ImageProcessor;
use Uptms\Media\Services\MediaStorage;

/**
 * Validates an upload by its content and writes the safe copy to disk.
 * Shared by upload and replace. The stored name and extension are ours,
 * never the client's, so `photo.php.jpg` can't become executable.
 *
 * The disks run with `throw => false`, so a write that does not land returns
 * false instead of raising. Unchecked, the caller would still commit a Media
 * row and the library would list an image that is not on disk.
 */
final readonly class StoreUploadedFile
{
    public const array DOCUMENTS = ['application/pdf' => 'pdf'];

    public function __construct(
        private ImageProcessor $images,
        private MediaStorage $storage,
    ) {}

    /**
     * @return array{disk: string, path: string, mime: string, kind: string, size: int, original_size: int, width: int|null, height: int|null}
     */
    public function store(UploadedFile $file, string $uuid, string $filename): array
    {
        $mime = (string) $file->getMimeType();
        $originalSize = (int) $file->getSize();
        $disk = $this->storage->diskName();

        if (isset(ImageProcessor::FORMATS[$mime])) {
            if ($originalSize > (int) config('media.max_image_kb') * 1024) {
                throw ValidationException::withMessages(['file' => 'Images can be up to 10 MB.']);
            }

            $image = $this->images->sanitize((string) $file->getRealPath(), $mime);
            $path = $this->storage->originalPath($uuid, $filename, $image['extension']);

            if ($this->storage->disk($disk)->put($path, $image['binary'], ['visibility' => 'public']) === false) {
                throw new RuntimeException(sprintf('Could not write %s to the [%s] disk.', $path, $disk));
            }

            return [
                'disk' => $disk,
                'path' => $path,
                'mime' => $mime,
                'kind' => Media::IMAGE,
                'size' => strlen($image['binary']),
                'original_size' => $originalSize,
                'width' => $image['width'],
                'height' => $image['height'],
            ];
        }

        if (isset(self::DOCUMENTS[$mime])) {
            $path = $this->storage->originalPath($uuid, $filename, self::DOCUMENTS[$mime]);

            if ($this->storage->disk($disk)->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'public']) === false) {
                throw new RuntimeException(sprintf('Could not write %s to the [%s] disk.', $path, $disk));
            }

            return [
                'disk' => $disk,
                'path' => $path,
                'mime' => $mime,
                'kind' => Media::DOCUMENT,
                'size' => $originalSize,
                'original_size' => $originalSize,
                'width' => null,
                'height' => null,
            ];
        }

        throw ValidationException::withMessages(['file' => 'Upload a JPG, PNG, WebP or PDF file.']);
    }
}
