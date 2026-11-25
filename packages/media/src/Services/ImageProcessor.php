<?php

declare(strict_types=1);

namespace Uptms\Media\Services;

use GdImage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * GD-based image pipeline. Every image is decoded and re-encoded, which
 * drops EXIF/XMP/ICC metadata (GPS position, camera serials) and any
 * payload smuggled after the image data. Orientation is applied first so
 * photos from phones don't end up sideways once the EXIF flag is gone.
 */
final class ImageProcessor
{
    /** Formats accepted on upload and the encoder used to store them. */
    public const array FORMATS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{binary: string, width: int, height: int, mime: string, extension: string}
     */
    public function sanitize(string $path, string $mime): array
    {
        if (! isset(self::FORMATS[$mime])) {
            throw ValidationException::withMessages(['file' => 'Upload a JPG, PNG or WebP image.']);
        }

        $info = @getimagesize($path);

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages(['file' => 'This file is not a readable image.']);
        }

        if ((int) config('media.max_pixels') < $info[0] * $info[1]) {
            throw ValidationException::withMessages(['file' => 'This image is too large. Keep it under 40 megapixels.']);
        }

        $contents = file_get_contents($path);
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['file' => 'This file is not a readable image.']);
        }

        // Palette (8-bit) PNGs can't be encoded as AVIF or resampled cleanly.
        imagepalettetotruecolor($image);

        if ($mime === 'image/jpeg') {
            $image = $this->applyOrientation($image, $path);
        }

        $image = $this->fit($image, (int) config('media.max_edge'));

        return [
            'binary' => $this->encode($image, self::FORMATS[$mime]),
            'width' => imagesx($image),
            'height' => imagesy($image),
            'mime' => $mime,
            'extension' => self::FORMATS[$mime],
        ];
    }

    /**
     * @return array{binary: string, width: int, height: int}
     */
    public function variant(string $binary, int $width, string $format): array
    {
        $image = @imagecreatefromstring($binary);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Stored image could not be decoded.');
        }

        imagepalettetotruecolor($image);

        $image = $this->fit($image, $width, widthOnly: true);

        return [
            'binary' => $this->encode($image, $format),
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
    }

    private function applyOrientation(GdImage $image, string $path): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            $image = $rotated instanceof GdImage ? $rotated : $image;
        }

        return $image;
    }

    /** Scale down (never up) so the longest edge — or the width — fits. */
    private function fit(GdImage $image, int $limit, bool $widthOnly = false): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $edge = $widthOnly ? $width : max($width, $height);

        if ($edge <= $limit) {
            return $image;
        }

        $ratio = $limit / $edge;
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('Could not allocate an image canvas.');
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function encode(GdImage $image, string $format): string
    {
        imagesavealpha($image, true);
        ob_start();

        $ok = match ($format) {
            'jpg' => imagejpeg($image, null, 85),
            'png' => imagepng($image, null, 6),
            'webp' => imagewebp($image, null, 80),
            'avif' => imageavif($image, null, 55, 6),
            default => throw new RuntimeException("Unsupported image format {$format}."),
        };

        $binary = (string) ob_get_clean();

        if (! $ok || $binary === '') {
            throw new RuntimeException("Encoding {$format} failed.");
        }

        return $binary;
    }
}
