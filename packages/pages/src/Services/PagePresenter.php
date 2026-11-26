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
 * section numbers assigned in order, media ids replaced by image objects,
 * document links given their file's current address (a trashed or purged
 * document drops the link) and every block's source data attached under
 * `sources`.
 *
 * @phpstan-import-type PublicDocument from MediaPresenter
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
        $documentIds = [];

        foreach ($blocks as $block) {
            $type = $this->types->get($block['type']);
            $mediaIds = array_merge($mediaIds, array_column($this->validator->mediaIn($type->fields, $block['data']), 'media_id'));
            $documentIds = array_merge($documentIds, $this->validator->documentsIn($type->fields, $block['data']));
        }

        $images = $this->media->presentMany(array_values(array_diff($mediaIds, $documentIds)));
        $documents = [];

        foreach (array_unique($documentIds) as $documentId) {
            $documents[$documentId] = $this->media->presentDocument($documentId);
        }
        $number = 0;
        $out = [];

        foreach ($blocks as $block) {
            $type = $this->types->get($block['type']);
            $data = $this->withMedia($type->fields, $block['data'], $images, $documents);
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
     * @param  array<int, PublicDocument|null>  $documents
     * @return array<string, mixed>
     */
    private function withMedia(array $fields, array $data, array $images, array $documents): array
    {
        foreach ($fields as $field) {
            $value = $data[$field->name] ?? null;

            if ($field->type === 'image') {
                $data[$field->name] = is_int($value) ? $images[$value] ?? null : null;
            } elseif ($field->type === 'document') {
                $data[$field->name] = is_int($value) ? $documents[$value] ?? null : null;
            } elseif ($field->type === 'link' && is_array($value)) {
                $data[$field->name] = $this->link($value, $documents);
            } elseif ($field->type === 'repeater' && is_array($value)) {
                $data[$field->name] = $this->rows($field, $value, $images, $documents);
            }
        }

        return $data;
    }

    /**
     * Repeater rows; in link rows (label, href, document) a chosen document
     * becomes the href and a missing one drops the row.
     *
     * @param  array<mixed>  $rows
     * @param  array<int, array<string, mixed>>  $images
     * @param  array<int, PublicDocument|null>  $documents
     * @return list<mixed>
     */
    private function rows(Field $field, array $rows, array $images, array $documents): array
    {
        $documentField = $this->validator->documentField($field->fields);
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $out[] = $row;

                continue;
            }

            /** @var array<string, mixed> $row */
            $documentId = $documentField !== null ? $row[$documentField] ?? null : null;
            $row = $this->withMedia($field->fields, $row, $images, $documents);

            if ($documentField !== null && array_key_exists('href', $row)) {
                unset($row[$documentField]);

                if (is_int($documentId)) {
                    $document = $documents[$documentId] ?? null;

                    if ($document === null) {
                        continue;
                    }

                    $row['href'] = self::sitePath($document['url']);
                }
            }

            $out[] = $row;
        }

        return $out;
    }

    /**
     * The public link is always {label, href}.
     *
     * @param  array<mixed>  $link
     * @param  array<int, PublicDocument|null>  $documents
     * @return array{label: mixed, href: mixed}
     */
    private function link(array $link, array $documents): array
    {
        $mediaId = $link['media_id'] ?? null;

        if (! is_int($mediaId)) {
            return ['label' => $link['label'] ?? '', 'href' => $link['href'] ?? ''];
        }

        $document = $documents[$mediaId] ?? null;

        return $document === null
            ? ['label' => '', 'href' => '']
            : ['label' => $link['label'] ?? '', 'href' => self::sitePath($document['url'])];
    }

    /** /storage is served on the site's domain, so document links stay site-relative. */
    private static function sitePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }
}
