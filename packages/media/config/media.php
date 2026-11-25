<?php

declare(strict_types=1);

return [
    // Public disk the website reads images from (docs/ARCHITECTURE.md §9).
    'disk' => env('MEDIA_DISK', 'public'),

    // Responsive widths generated for every image, in each format below.
    'widths' => [480, 960, 1600],
    'formats' => ['avif', 'webp'],

    // Originals are re-encoded no larger than this (longest edge).
    'max_edge' => 2400,

    // Upload limits. Pixel cap guards against decompression bombs.
    'max_image_kb' => 10_240,
    'max_document_kb' => 20_480,
    'max_pixels' => 40_000_000,

    // Trashed files are purged after this many days.
    'trash_days' => 30,
];
