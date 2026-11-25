<?php

declare(strict_types=1);

namespace Uptms\Media\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Uptms\Media\Models\Media;

/**
 * A media id that may be placed on the website: it exists, isn't in the
 * trash, has the expected kind and — for images — alt text or the
 * decorative flag ("Images without it can't be added to pages", Figma 18).
 */
final readonly class PlaceableMedia implements ValidationRule
{
    public function __construct(private string $kind = Media::IMAGE) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $media = is_numeric($value) ? Media::query()->find((int) $value) : null;

        if (! $media instanceof Media) {
            $fail('Choose a file from the media library.');

            return;
        }

        if ($media->kind !== $this->kind) {
            $fail($this->kind === Media::IMAGE ? 'Choose an image.' : 'Choose a document.');

            return;
        }

        if ($media->needsAltText()) {
            $fail("Add alt text to {$media->filename} in the media library first.");
        }
    }
}
