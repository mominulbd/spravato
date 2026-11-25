<?php

declare(strict_types=1);

namespace Uptms\Pages\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PageContent;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Models\Revision;

/**
 * Freezes the working copy now and publishes it at `$at`
 * (pages:publish-scheduled, every minute). Edits made afterwards are not
 * part of the scheduled version. A live page stays live until then.
 */
final readonly class SchedulePageAction
{
    public function __construct(
        private PageContent $content,
        private AuditRecorder $audit,
        private SavePageContentAction $writer,
    ) {}

    public function execute(Page $page, Carbon $at): Page
    {
        $this->writer->assertComplete($page);

        DB::transaction(function () use ($page, $at): void {
            $revision = $page->recordRevision(Revision::SCHEDULED, $this->content->snapshot($page), 'Scheduled for '.$at->toIso8601String());

            $page->forceFill([
                'status' => $page->status === PublishStatus::Published ? PublishStatus::Published : PublishStatus::Scheduled,
                'scheduled_at' => $at,
                'scheduled_revision_id' => $revision->id,
            ])->saveQuietly();

            $this->audit->record('page.scheduled', $page, null, ['scheduled_at' => $at->toIso8601String()]);
        });

        return $page;
    }

    public function cancel(Page $page): Page
    {
        DB::transaction(function () use ($page): void {
            $page->forceFill([
                'status' => $page->published_revision_id !== null ? PublishStatus::Published : PublishStatus::Draft,
                'scheduled_at' => null,
                'scheduled_revision_id' => null,
            ])->saveQuietly();

            $this->audit->record('page.unscheduled', $page);
        });

        return $page;
    }
}
