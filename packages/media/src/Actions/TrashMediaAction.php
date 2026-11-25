<?php

declare(strict_types=1);

namespace Uptms\Media\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\Media\Models\Media;

/**
 * Moves files to the 30-day trash. A file still placed somewhere can't be
 * trashed ("To delete, remove it from 3 pages first", Figma 18).
 */
final class TrashMediaAction
{
    /**
     * @param  list<int>  $ids
     */
    public function execute(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $media = Media::query()->withCount('usages')->whereIn('id', $ids)->lockForUpdate()->get();
            $inUse = $media->filter(fn (Media $item): bool => (int) $item->usages_count > 0);

            if ($inUse->isNotEmpty()) {
                throw new GenericDomainException(ErrorCode::Conflict, $inUse->count() === 1
                    ? 'This file is still used on the website. Remove it from those pages first.'
                    : $inUse->count().' of these files are still used on the website. Remove them from those pages first.', [
                        'in_use' => $inUse->map(fn (Media $item): array => ['id' => $item->id, 'filename' => $item->filename, 'usages' => (int) $item->usages_count])->values()->all(),
                    ]);
            }

            $media->each(fn (Media $item) => $item->delete());

            return $media->count();
        });
    }
}
