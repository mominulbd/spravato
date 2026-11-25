<?php

declare(strict_types=1);

namespace Uptms\Media\Console\Commands;

use Illuminate\Console\Command;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Media\Models\Media;
use Uptms\Media\Services\MediaStorage;

/** Scheduled daily: files trashed more than 30 days ago are deleted for good. */
final class PurgeTrashedMediaCommand extends Command
{
    protected $signature = 'media:purge-trash';

    protected $description = 'Permanently delete media that has been in the trash longer than the retention period';

    public function handle(MediaStorage $storage, AuditRecorder $audit): int
    {
        $count = 0;

        Media::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays((int) config('media.trash_days')))
            ->with('variants')
            ->chunkById(100, function ($items) use ($storage, $audit, &$count): void {
                foreach ($items as $media) {
                    /** @var Media $media */
                    $storage->deleteFiles($media);
                    $audit->record('media.purged', $media);
                    $media->forceDelete();
                    $count++;
                }
            });

        $this->info("Purged {$count} file(s).");

        return self::SUCCESS;
    }
}
