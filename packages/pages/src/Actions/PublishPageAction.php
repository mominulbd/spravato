<?php

declare(strict_types=1);

namespace Uptms\Pages\Actions;

use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PageContent;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Publishing\Models\Revision;

/**
 * Makes the working copy live: snapshot → published revision → the public
 * API serves it, and the website is told to revalidate.
 */
final readonly class PublishPageAction
{
    public function __construct(
        private PageContent $content,
        private AuditRecorder $audit,
        private SavePageContentAction $writer,
    ) {}

    public function execute(Page $page, ?Revision $revision = null): Page
    {
        if ($revision === null) {
            $this->writer->assertComplete($page);
        }

        DB::transaction(function () use ($page, $revision): void {
            $revision ??= $page->recordRevision(Revision::PUBLISHED, $this->content->snapshot($page));
            $from = $page->status;

            /** @var list<array{type: string, data: array<string, mixed>, is_visible: bool}> $blocks */
            $blocks = $revision->payload['blocks'];

            $page->forceFill([
                'status' => PublishStatus::Published,
                'published_revision_id' => $revision->id,
                'published_hash' => $this->content->hash($blocks),
                'published_at' => $page->published_at ?? now(),
                'scheduled_at' => null,
                'scheduled_revision_id' => null,
            ])->saveQuietly();

            $page->unsetRelation('publishedRevision');
            $this->writer->syncMedia($page);
            $this->audit->record('page.published', $page, ['status' => $from->value], ['status' => 'published', 'revision_id' => $revision->id]);
        });

        ContentPublished::dispatch(['content', 'page:'.$page->cacheKey()]);

        return $page;
    }
}
