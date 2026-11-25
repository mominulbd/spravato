<?php

declare(strict_types=1);

namespace Uptms\Pages\Actions;

use Illuminate\Support\Facades\DB;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PageContent;
use Uptms\Publishing\Models\Revision;

/**
 * Copies an old version into the working copy (never straight to the
 * site); staff review it and publish as usual. Blocks of types that no
 * longer exist are skipped by validation.
 */
final readonly class RestoreRevisionAction
{
    public function __construct(
        private SavePageContentAction $writer,
        private PageContent $content,
    ) {}

    public function execute(Page $page, Revision $revision, int $lockVersion, ?int $actorId): Page
    {
        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_map(fn (array $block): array => [...$block, 'id' => null], $revision->payload['blocks'] ?? []);

        return DB::transaction(function () use ($page, $revision, $blocks, $lockVersion, $actorId): Page {
            $page = $this->writer->execute($page, $blocks, $lockVersion, $actorId);
            $page->recordRevision(Revision::RESTORED, $this->content->snapshot($page), 'Restored from revision #'.$revision->id);

            return $page;
        });
    }
}
