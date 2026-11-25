<?php

declare(strict_types=1);

namespace Uptms\Pages\Actions;

use Illuminate\Support\Facades\DB;
use Uptms\Pages\Blocks\BlockTypes;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PageContent;
use Uptms\Pages\Services\PagePaths;
use Uptms\Publishing\Domain\Enums\PublishStatus;

/** New pages start as drafts with the template's starter sections. */
final readonly class CreatePageAction
{
    public function __construct(
        private PagePaths $paths,
        private BlockTypes $types,
        private PageContent $content,
    ) {}

    public function execute(string $title, string $slug, PageTemplate $template, ?Page $parent, ?int $actorId): Page
    {
        $path = $this->paths->build($parent, $slug);
        $this->paths->assertAvailable($path);

        return DB::transaction(function () use ($title, $slug, $template, $parent, $actorId, $path): Page {
            $page = new Page(['title' => $title]);
            $page->forceFill([
                'slug' => $slug,
                'path' => $path,
                'parent_id' => $parent?->id,
                'template' => $template,
                'status' => PublishStatus::Draft,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'content_updated_at' => now(),
            ])->save();

            $blocks = [];

            foreach ($template->starterBlocks() as $position => $type) {
                $data = $this->types->get($type)->defaults();

                if (array_key_exists('headline', $data)) {
                    $data['headline'] = $position === 0 ? $title : '';
                }

                $blocks[] = ['type' => $type, 'data' => $data, 'is_visible' => true];
                $page->blocks()->create([...end($blocks), 'sort_order' => $position + 1]);
            }

            $page->forceFill(['draft_hash' => $this->content->hash($blocks)])->saveQuietly();

            return $page;
        });
    }
}
