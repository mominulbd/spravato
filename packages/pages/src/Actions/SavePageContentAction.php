<?php

declare(strict_types=1);

namespace Uptms\Pages\Actions;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\DB;
use Uptms\Media\Services\MediaUsageRecorder;
use Uptms\Pages\Blocks\BlockTypes;
use Uptms\Pages\Blocks\BlockValidator;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PageContent;
use Uptms\Publishing\Models\Revision;

/**
 * Saves the working copy (autosave and "Save draft", Figma 07). The live
 * site is untouched until Publish. `lock_version` must match, so a second
 * editor in another tab gets CONFLICT instead of silently overwriting.
 */
final readonly class SavePageContentAction
{
    public function __construct(
        private BlockValidator $validator,
        private BlockTypes $types,
        private PageContent $content,
        private MediaUsageRecorder $usages,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function execute(Page $page, array $blocks, int $lockVersion, ?int $actorId, bool $checkpoint = false, ?string $note = null): Page
    {
        $clean = $this->validator->validate($blocks, strict: false);

        return DB::transaction(function () use ($page, $clean, $lockVersion, $actorId, $checkpoint, $note): Page {
            $current = (int) Page::query()->whereKey($page->id)->lockForUpdate()->value('lock_version');

            if ($current !== $lockVersion) {
                throw new GenericDomainException(ErrorCode::Conflict, 'Someone else saved this page since you opened it. Reload to see their changes.', [
                    'lock_version' => ['Expected '.$current.'.'],
                ]);
            }

            $this->replaceBlocks($page, $clean);

            $page->forceFill([
                'lock_version' => $current + 1,
                'draft_hash' => $this->content->hash($clean),
                'content_updated_at' => now(),
                'updated_by' => $actorId,
            ])->saveQuietly();

            $page->unsetRelation('blocks');
            $this->syncMedia($page);

            if ($checkpoint) {
                $page->recordRevision(Revision::DRAFT, $this->content->snapshot($page), $note);
            }

            return $page;
        });
    }

    /**
     * @param  list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>  $blocks
     */
    public function replaceBlocks(Page $page, array $blocks): void
    {
        $existing = $page->blocks()->pluck('id')->all();
        $kept = [];

        foreach ($blocks as $position => $block) {
            $attributes = ['type' => $block['type'], 'data' => $block['data'], 'is_visible' => $block['is_visible'], 'sort_order' => $position + 1];

            if ($block['id'] !== null && in_array($block['id'], $existing, true)) {
                $page->blocks()->whereKey($block['id'])->update([...$attributes, 'data' => json_encode($block['data'])]);
                $kept[] = $block['id'];
            } else {
                $kept[] = $page->blocks()->create($attributes)->id;
            }
        }

        $page->blocks()->whereNotIn('id', $kept)->delete();
    }

    /**
     * Publishing and scheduling need every required field filled. Errors
     * come back keyed `blocks.{i}.data.{field}` like a save's.
     */
    public function assertComplete(Page $page): void
    {
        $page->loadMissing('blocks');
        $this->validator->validate(array_values($page->blocks->map(fn ($block): array => [
            'id' => $block->id,
            'type' => $block->type,
            'data' => $block->data,
            'is_visible' => $block->is_visible,
        ])->all()));
    }

    /**
     * Files in the working copy and in the live version both count as used:
     * removing an image from a draft must not free a file the site shows.
     */
    public function syncMedia(Page $page): void
    {
        $page->loadMissing(['blocks', 'publishedRevision']);
        $blocks = $page->blocks->map(fn ($block): array => ['type' => $block->type, 'data' => $block->data])->all();

        /** @var list<array{type: string, data: array<string, mixed>}> $live */
        $live = $page->publishedRevision?->payload['blocks'] ?? [];
        $usages = [];

        foreach ([...$blocks, ...$live] as $block) {
            if ($this->types->has($block['type'])) {
                $type = $this->types->get($block['type']);
                $usages = array_merge($usages, $this->validator->mediaIn($type->fields, $block['data'], $type->label.' ·'));
            }
        }

        $this->usages->sync($page, array_map(fn (array $usage): array => ['media_id' => $usage['media_id'], 'context' => rtrim($usage['context'], ' ·')], $usages), $page->title, '/pages/'.$page->id);
    }
}
