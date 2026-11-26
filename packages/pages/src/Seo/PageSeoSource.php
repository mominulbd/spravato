<?php

declare(strict_types=1);

namespace Uptms\Pages\Seo;

use Illuminate\Database\Eloquent\Model;
use Uptms\Media\Services\MediaPresenter;
use Uptms\Pages\Blocks\BlockTypes;
use Uptms\Pages\Blocks\Field;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Pages\Models\Page;
use Uptms\Pages\Services\PageContent;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Services\BlockSources;
use Uptms\Seo\Contracts\SeoSource;
use Uptms\Seo\Domain\SeoSubject;

/**
 * Describes a page to the SEO module: its text, headings, links and images
 * read from the blocks, and the FAQs and clinicians its blocks show (for
 * the JSON-LD graph). FAQs marked "leave out of schema" still count as text.
 */
final readonly class PageSeoSource implements SeoSource
{
    public function __construct(
        private BlockTypes $types,
        private PageContent $content,
        private BlockSources $sources,
        private MediaPresenter $media,
    ) {}

    public function type(): string
    {
        return 'page';
    }

    public function label(): string
    {
        return 'Pages';
    }

    public function model(int $id): ?Model
    {
        return Page::query()->find($id);
    }

    public function subject(int $id, bool $live = false): ?SeoSubject
    {
        $page = Page::query()->with(['blocks', 'publishedRevision'])->find($id);

        if (! $page instanceof Page) {
            return null;
        }

        $snapshot = $live ? $this->liveSnapshot($page) : $this->content->snapshot($page);

        return $snapshot === null ? null : $this->describe($page, $snapshot);
    }

    public function published(): iterable
    {
        $pages = Page::query()->with('publishedRevision')->where('status', PublishStatus::Published)->orderBy('path')->get();

        foreach ($pages as $page) {
            $snapshot = $this->liveSnapshot($page);

            if ($snapshot !== null) {
                yield $this->describe($page, $snapshot);
            }
        }
    }

    /** @return array{title: string, blocks: list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>}|null */
    private function liveSnapshot(Page $page): ?array
    {
        if ($page->status !== PublishStatus::Published) {
            return null;
        }

        /** @var array{title: string, blocks: list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>}|null */
        return $page->publishedRevision?->payload;
    }

    /**
     * @param  array{title: string, blocks: list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>}  $snapshot
     */
    public function describe(Page $page, array $snapshot): SeoSubject
    {
        $text = [];
        $headings = [];
        $links = [];
        $mediaIds = [];
        $faqs = [];
        $people = [];
        $firstParagraph = '';
        $h1Seen = false;

        foreach ($snapshot['blocks'] as $block) {
            if (! $block['is_visible'] || ! $this->types->has($block['type'])) {
                continue;
            }

            $type = $this->types->get($block['type']);
            $this->walk($type->fields, $block['data'], $text, $headings, $links, $mediaIds, $firstParagraph, $h1Seen);

            foreach ($type->sourcesFor($block['data']) as $as => [$source, $params]) {
                $resolved = $this->sources->resolve($source, $params);

                if (! is_array($resolved)) {
                    continue;
                }

                foreach ($resolved as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    if ($source === 'faqs' && isset($item['question'], $item['answer'])) {
                        if (($item['in_schema'] ?? true) !== false) {
                            $faqs[] = ['question' => (string) $item['question'], 'answer' => (string) $item['answer']];
                        }
                        $text[] = $item['question']."\n".$item['answer'];
                    } elseif ($source === 'team' && isset($item['name'])) {
                        $people[$item['name']] = [
                            'name' => (string) $item['name'],
                            'credentials' => is_string($item['credentials'] ?? null) ? $item['credentials'] : null,
                            'role' => is_string($item['role_title'] ?? null) ? $item['role_title'] : null,
                            'image' => is_array($item['photo'] ?? null) ? ($item['photo']['url'] ?? null) : null,
                            'schema_type' => ($item['schema_type'] ?? null) === 'Physician' ? 'Physician' : 'Person',
                        ];
                    }
                }
            }
        }

        $described = $this->media->describeMany($mediaIds);
        $images = [];

        foreach ($mediaIds as $id) {
            $images[] = ['id' => $id, 'alt' => $described[$id]['alt'] ?? '', 'filename' => $described[$id]['filename'] ?? ''];
        }

        return new SeoSubject(
            type: 'page',
            id: $page->id,
            label: $snapshot['title'],
            path: $page->path,
            adminPath: '/pages/'.$page->id,
            published: $page->status === PublishStatus::Published,
            title: $snapshot['title'],
            templateKey: 'page',
            description: null,
            bodyText: implode("\n\n", array_filter(array_map('trim', $text))),
            firstParagraph: $firstParagraph,
            headings: $headings,
            images: $images,
            links: array_values(array_filter($links)),
            imageId: $mediaIds[0] ?? null,
            publishedAt: $page->published_at,
            updatedAt: $page->content_updated_at ?? $page->updated_at,
            isHome: $page->is_home,
            defaultSchemaType: match ($page->template) {
                PageTemplate::Contact => 'ContactPage',
                PageTemplate::Landing => 'MedicalWebPage',
                PageTemplate::Hub => 'CollectionPage',
                default => 'WebPage',
            },
            faqs: $faqs,
            people: array_values($people),
            breadcrumbs: $this->parents($page),
            sitemapPriority: $page->is_home ? 1.0 : 0.8,
        );
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $data
     * @param  list<string>  $text
     * @param  list<string>  $headings
     * @param  list<string>  $links
     * @param  list<int>  $mediaIds
     */
    private function walk(array $fields, array $data, array &$text, array &$headings, array &$links, array &$mediaIds, string &$firstParagraph, bool &$h1Seen): void
    {
        foreach ($fields as $field) {
            $value = $data[$field->name] ?? null;

            if ($field->name === 'anchor' || $value === null || $value === '') {
                continue;
            }

            switch ($field->type) {
                case 'headline':
                    $plain = str_replace('*', '', (string) $value);
                    $text[] = $plain;
                    if ($h1Seen) {
                        $headings[] = $plain;
                    }
                    $h1Seen = true;
                    break;
                case 'text':
                case 'textarea':
                    $text[] = (string) $value;
                    if ($field->type === 'textarea' && $firstParagraph === '' && str_word_count((string) $value) >= 8) {
                        $firstParagraph = (string) $value;
                    }
                    break;
                case 'markdown':
                    preg_match_all('/\[([^\]]+)\]\(([^)\s]+)\)/', (string) $value, $matches);
                    array_push($links, ...$matches[2]);
                    $plain = (string) preg_replace(['/\[([^\]]+)\]\([^)]+\)/', '/[*_`#>]+/'], ['$1', ''], (string) $value);
                    $text[] = $plain;
                    if ($firstParagraph === '') {
                        $firstParagraph = trim((string) strtok($plain, "\n"));
                    }
                    break;
                case 'link':
                    if (is_array($value)) {
                        $text[] = (string) ($value['label'] ?? '');
                        $links[] = (string) ($value['href'] ?? '');
                    }
                    break;
                case 'list':
                    if (is_array($value)) {
                        $text[] = implode("\n", array_map(fn (mixed $item): string => is_string($item) ? $item : '', $value));
                    }
                    break;
                case 'image':
                    if (is_int($value)) {
                        $mediaIds[] = $value;
                    }
                    break;
                case 'repeater':
                    if (is_array($value)) {
                        foreach ($value as $row) {
                            if (is_array($row)) {
                                /** @var array<string, mixed> $row */
                                $this->walk($field->fields, $row, $text, $headings, $links, $mediaIds, $firstParagraph, $h1Seen);
                            }
                        }
                    }
                    break;
            }
        }
    }

    /** @return list<array{name: string, path: string}> */
    private function parents(Page $page): array
    {
        $crumbs = [];
        $parent = $page->parent_id !== null ? Page::query()->find($page->parent_id) : null;

        for ($guard = 0; $parent instanceof Page && $guard < 10; $guard++) {
            if (! $parent->is_home) {
                array_unshift($crumbs, ['name' => $parent->title, 'path' => $parent->path]);
            }
            $parent = $parent->parent_id !== null ? Page::query()->find($parent->parent_id) : null;
        }

        return $crumbs;
    }
}
