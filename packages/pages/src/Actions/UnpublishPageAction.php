<?php

declare(strict_types=1);

namespace Uptms\Pages\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Pages\Models\Page;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Events\ContentPublished;

/** Takes a page off the website; its content stays as a draft. */
final readonly class UnpublishPageAction
{
    public function __construct(
        private AuditRecorder $audit,
        private SavePageContentAction $writer,
    ) {}

    public function execute(Page $page): Page
    {
        if ($page->is_home) {
            throw new GenericDomainException(ErrorCode::Conflict, 'The home page is always published. Publish a new version instead.');
        }

        DB::transaction(function () use ($page): void {
            $from = $page->status;
            $page->forceFill([
                'status' => PublishStatus::Draft,
                'published_revision_id' => null,
                'published_hash' => null,
                'scheduled_at' => null,
                'scheduled_revision_id' => null,
            ])->saveQuietly();

            $page->unsetRelation('publishedRevision');
            $this->writer->syncMedia($page);
            $this->audit->record('page.unpublished', $page, ['status' => $from->value], ['status' => 'draft']);
        });

        ContentPublished::dispatch(['content', 'page:'.$page->cacheKey()]);

        return $page;
    }
}
