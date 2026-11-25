<?php

declare(strict_types=1);

namespace Uptms\Media\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Uptms\Media\Models\Media;
use Uptms\Media\Models\MediaUsage;
use Uptms\Media\Models\MediaVariant;

/**
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $thumb = $this->relationLoaded('variants')
            ? $this->variants->where('format', 'webp')->sortBy('width')->first()
            : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'filename' => $this->filename,
            'original_name' => $this->original_name,
            'kind' => $this->kind,
            'mime' => $this->mime,
            'url' => $this->url(),
            'thumbnail_url' => $thumb instanceof MediaVariant ? Storage::disk($this->disk)->url($thumb->path) : ($this->isImage() ? $this->url() : null),
            'size' => $this->size,
            'original_size' => $this->original_size,
            'delivered_size' => $this->deliveredSize(),
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt,
            'is_decorative' => $this->is_decorative,
            'needs_alt_text' => $this->needsAltText(),
            'caption' => $this->caption,
            'focal' => ['x' => $this->focal_x, 'y' => $this->focal_y],
            'folder_id' => $this->folder_id,
            'variants_ready' => ! $this->isImage() || ($this->relationLoaded('variants') && $this->variants->isNotEmpty()),
            'usages_count' => $this->usages_count ?? ($this->relationLoaded('usages') ? $this->usages->count() : null),
            'usage_state' => $this->usageState(),
            'usages' => $this->whenLoaded('usages', fn () => $this->usages->map(fn (MediaUsage $usage): array => [
                'label' => $usage->label,
                'context' => $usage->context,
                'admin_path' => $usage->admin_path,
            ])->values()->all()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn (MediaVariant $variant): array => [
                'format' => $variant->format,
                'width' => $variant->width,
                'height' => $variant->height,
                'size' => $variant->size,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'first_used_at' => $this->first_used_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    /** in_use | never_used | removed (Figma 18b "Why they're unused"). */
    private function usageState(): ?string
    {
        $count = $this->usages_count ?? ($this->relationLoaded('usages') ? $this->usages->count() : null);

        return match (true) {
            $count === null => null,
            $count > 0 => 'in_use',
            $this->first_used_at === null => 'never_used',
            default => 'removed',
        };
    }
}
