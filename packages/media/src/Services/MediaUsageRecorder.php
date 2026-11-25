<?php

declare(strict_types=1);

namespace Uptms\Media\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Uptms\Media\Models\Media;
use Uptms\Media\Models\MediaUsage;

/**
 * The one writer of media_usages, called by every module that places a
 * file. `sync` replaces the owner's usages, so removing an image from a
 * block frees it; files that lose their last usage are stamped so the
 * library can say "Removed from Home".
 */
class MediaUsageRecorder
{
    /**
     * @param  list<array{media_id: int, context: string}>  $usages
     */
    public function sync(Model $owner, array $usages, string $label, ?string $adminPath = null): void
    {
        $type = $owner->getMorphClass();
        $id = (int) $owner->getKey();

        DB::transaction(function () use ($usages, $label, $adminPath, $type, $id): void {
            $before = MediaUsage::query()->where('usable_type', $type)->where('usable_id', $id)->pluck('media_id')->all();

            MediaUsage::query()->where('usable_type', $type)->where('usable_id', $id)->delete();

            $keep = [];

            foreach ($usages as $usage) {
                $key = $usage['media_id'].'|'.$usage['context'];

                if (isset($keep[$key])) {
                    continue;
                }

                $keep[$key] = true;

                MediaUsage::query()->create([
                    'media_id' => $usage['media_id'],
                    'usable_type' => $type,
                    'usable_id' => $id,
                    'context' => mb_substr($usage['context'], 0, 80),
                    'label' => mb_substr($label, 0, 190),
                    'admin_path' => $adminPath,
                ]);
            }

            $now = array_values(array_unique(array_column($usages, 'media_id')));

            if ($now !== []) {
                Media::withTrashed()->whereIn('id', $now)->whereNull('first_used_at')->update(['first_used_at' => now()]);
            }

            $released = array_diff(array_unique($before), $now);

            if ($released !== []) {
                Media::withTrashed()->whereIn('id', $released)->whereDoesntHave('usages')->update(['last_used_at' => now()]);
            }
        });
    }

    public function clear(Model $owner): void
    {
        $this->sync($owner, [], '');
    }
}
