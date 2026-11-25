<?php

declare(strict_types=1);

namespace Uptms\Media\Services;

use Illuminate\Support\Facades\Storage;
use Uptms\Media\Models\Media;
use Uptms\Media\Models\MediaVariant;

/**
 * The image shape the website receives. Other modules hand over ids and
 * get this back, so no module reads the media tables directly.
 *
 * @phpstan-type PublicImage array{id: int, url: string, width: int|null, height: int|null, alt: string, focal: array{x: float, y: float}, sources: array<string, list<array{width: int, url: string}>>}
 */
class MediaPresenter
{
    /**
     * @return PublicImage|null
     */
    public function present(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }

        return $this->presentMany([$id])[$id] ?? null;
    }

    /**
     * Trashed files are left out, so the site never links to a file that is
     * about to be purged.
     *
     * @param  list<int>  $ids
     * @return array<int, PublicImage>
     */
    public function presentMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        return Media::query()->with('variants')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn (Media $media): array => [$media->id => $this->toArray($media)])
            ->all();
    }

    /**
     * Alt text and file names for the SEO checklist. Decorative images
     * count as described: they are meant to be skipped by screen readers.
     *
     * @param  list<int>  $ids
     * @return array<int, array{alt: string, filename: string}>
     */
    public function describeMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        return Media::query()->whereIn('id', $ids)->get(['id', 'alt', 'is_decorative', 'filename'])
            ->mapWithKeys(fn (Media $media): array => [$media->id => [
                'alt' => $media->is_decorative ? '(decorative)' : (string) $media->alt,
                'filename' => $media->filename,
            ]])
            ->all();
    }

    /**
     * @return PublicImage
     */
    public function toArray(Media $media): array
    {
        $sources = [];

        foreach ($media->variants as $variant) {
            /** @var MediaVariant $variant */
            $sources[$variant->format][] = [
                'width' => $variant->width,
                'url' => Storage::disk($media->disk)->url($variant->path),
            ];
        }

        return [
            'id' => $media->id,
            'url' => $media->url(),
            'width' => $media->width,
            'height' => $media->height,
            'alt' => $media->is_decorative ? '' : (string) $media->alt,
            'focal' => ['x' => $media->focal_x, 'y' => $media->focal_y],
            'sources' => $sources,
        ];
    }
}
