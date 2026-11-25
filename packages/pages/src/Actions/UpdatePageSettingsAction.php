<?php

declare(strict_types=1);

namespace Uptms\Pages\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PagePaths;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Events\ContentPathChanged;
use Uptms\Publishing\Events\ContentPublished;

/**
 * Settings tab: title, address, parent and template. Moving a live page
 * changes its URL at once, so it needs content.publish (checked by the
 * policy); children move with it, and every live page that moved leaves a
 * 301 behind (ContentPathChanged → redirects module).
 */
final readonly class UpdatePageSettingsAction
{
    public function __construct(private PagePaths $paths) {}

    public function execute(Page $page, string $title, string $slug, PageTemplate $template, ?Page $parent, ?int $actorId): Page
    {
        if ($page->is_home && ($slug !== $page->slug || $parent !== null)) {
            throw new GenericDomainException(ErrorCode::ValidationFailed, 'The home page always lives at /.', ['slug' => ['The home page always lives at /.']]);
        }

        if ($parent !== null) {
            $this->paths->assertValidParent($page, $parent);
        }

        $path = $this->paths->build($parent, $slug, $page->is_home);
        $this->paths->assertAvailable($path, $page->id);
        $moved = $path !== $page->path;
        $oldKey = $page->cacheKey();
        $before = $moved ? $this->livePaths($page) : [];

        DB::transaction(function () use ($page, $title, $slug, $template, $parent, $actorId, $path, $moved): void {
            $page->fill(['title' => $title]);
            $page->forceFill([
                'slug' => $slug,
                'path' => $path,
                'parent_id' => $parent?->id,
                'template' => $template,
                'updated_by' => $actorId,
            ])->save();

            if ($moved) {
                $this->paths->cascade($page);
            }
        });

        if ($page->status === PublishStatus::Published && ($moved || $page->wasChanged('title'))) {
            ContentPublished::dispatch(['content', 'page:'.$oldKey, 'page:'.$page->cacheKey()]);
        }

        foreach ($before as $id => $oldPath) {
            $newPath = Page::query()->whereKey($id)->value('path');

            if (is_string($newPath) && $newPath !== $oldPath) {
                ContentPathChanged::dispatch($oldPath, $newPath);
            }
        }

        return $page;
    }

    /**
     * The live page and its live descendants, id → path, before a move.
     *
     * @return array<int, string>
     */
    private function livePaths(Page $page): array
    {
        $paths = [];
        $queue = [$page];

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($current->status === PublishStatus::Published) {
                $paths[$current->id] = $current->path;
            }

            foreach ($current->children()->get() as $child) {
                $queue[] = $child;
            }
        }

        return $paths;
    }
}
