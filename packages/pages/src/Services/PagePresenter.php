<?php

declare(strict_types=1);

namespace Uptms\Pages\Services;

use Uptms\Media\Services\MediaPresenter;
use Uptms\Pages\Blocks\BlockTypes;
use Uptms\Pages\Blocks\BlockValidator;
use Uptms\Pages\Blocks\Field;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Seo\PageSeoSource;
use Uptms\Publishing\Services\BlockSources;
use Uptms\Seo\Services\SeoPresenter;

/**
 * Turns a snapshot into what the website renders: hidden blocks dropped,
 * section numbers assigned in order, media ids replaced by image objects
 * and every block's source data attached under `sources`.
 */
final readonly class PagePresenter
{
    public function __construct(
        private BlockTypes $types,
        private BlockValidator $validator,
        private MediaPresenter $media,
        private BlockSources $sources,
        private PageSeoSource $seoSource,
        private SeoPresenter $seo,
    ) {}

    /**
     * @param  array{title: string, blocks: list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>}  $snapshot
     * @return array<string, mixed>
     */
    public function present(Page $page, array $snapshot, bool $preview = false): array
    {
        $blocks = array_values(array_filter($snapshot['blocks'], fn (array $block): bool => $block['is_visible'] && $this->types->has($block['type'])));

        $mediaIds = [];

        foreach ($blocks as $block) {
            $type = $this->types->get($block['type']);
            $mediaIds = array_merge($mediaIds, array_column($this->validator->mediaIn($type->fields, $block['data']), 'media_id'));
        }

        $images = $this->media->presentMany($mediaIds);
        $number = 0;
        $out = [];

        foreach ($blocks as $block) {
            $type = $this->types->get($block['type']);
            $data = $this->withImages($type->fields, $block['data'], $images);
            $rail = is_string($data['rail'] ?? null) && $data['rail'] !== '' ? str_pad((string) ++$number, 2, '0', STR_PAD_LEFT) : null;
            $sources = [];

            foreach ($type->sourcesFor($block['data']) as $as => [$source, $params]) {
                $sources[$as] = $this->sources->resolve($source, $params);
            }

            $out[] = [
                'id' => $block['id'],
                'type' => $block['type'],
                'anchor' => is_string($data['anchor'] ?? null) && $data['anchor'] !== '' ? $data['anchor'] : null,
                'number' => $rail,
                'data' => $data,
                'sources' => (object) $sources,
            ];
        }

        return [
            'id' => $page->id,
            'title' => $snapshot['title'],
            'path' => $page->path,
            'template' => $page->template->value,
            'is_home' => $page->is_home,
            'preview' => $preview,
            'published_at' => $page->published_at?->toIso8601String(),
            'updated_at' => ($page->content_updated_at ?? $page->updated_at)?->toIso8601String(),
            'blocks' => $out,
            'seo' => $this->seo($page, $snapshot, $preview),
        ];
    }

    /**
     * <head> tags and JSON-LD. Previews are never indexed.
     *
     * @param  array{title: string, blocks: list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>}  $snapshot
     * @return array<string, mixed>
     */
    private function seo(Page $page, array $snapshot, bool $preview): array
    {
        $seo = $this->seo->public($this->seoSource->describe($page, $snapshot));

        if ($preview) {
            $seo['robots'] = ['index' => false, 'follow' => false, 'max_image_preview' => 'none', 'max_snippet' => null];
        }

        return $seo;
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $images
     * @return array<string, mixed>
     */
    private function withImages(array $fields, array $data, array $images): array
    {
        foreach ($fields as $field) {
            $value = $data[$field->name] ?? null;

            if ($field->type === 'image') {
                $data[$field->name] = is_int($value) ? $images[$value] ?? null : null;
            } elseif ($field->type === 'repeater' && is_array($value)) {
                $data[$field->name] = array_map(fn (mixed $row): mixed => is_array($row) ? $this->withImages($field->fields, $row, $images) : $row, $value);
            }
        }

        return $data;
    }
}
