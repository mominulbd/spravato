<?php

declare(strict_types=1);

namespace Uptms\Seo\Domain;

use Illuminate\Support\Carbon;

/**
 * What a content module tells the SEO module about one page: its address, its text (for the checklist), its images and
 * links, and the facts the JSON-LD graph is built from. Plain data, so the
 * SEO module never reads another module's tables.
 */
final readonly class SeoSubject
{
    /**
     * @param  list<string>  $headings
     * @param  list<array{id: int|null, alt: string, filename: string}>  $images
     * @param  list<string>  $links  hrefs as written
     * @param  array<string, string>  $templateVars  extra title-template variables, e.g. ['category' => 'Guides']
     * @param  list<array{question: string, answer: string}>  $faqs
     * @param  list<array{name: string, credentials: string|null, role: string|null, image: string|null, schema_type: 'Person'|'Physician'}>  $people
     * @param  array{author: string|null, author_credentials: string|null, section: string|null, reviewer: string|null, reviewed_at: string|null}|null  $article
     * @param  list<array{name: string, path: string}>  $breadcrumbs  parents only; the subject itself is added last
     * @param  array{name: string|null, date: string|null}|null  $review  clinical review recorded by the content module
     */
    public function __construct(
        public string $type,
        public int $id,
        public string $label,
        public string $path,
        public string $adminPath,
        public bool $published,
        public string $title,
        public string $templateKey,
        public ?string $description = null,
        public string $bodyText = '',
        public string $firstParagraph = '',
        public array $headings = [],
        public array $images = [],
        public array $links = [],
        public ?int $imageId = null,
        public ?Carbon $publishedAt = null,
        public ?Carbon $updatedAt = null,
        public bool $isHome = false,
        public array $templateVars = [],
        public string $defaultSchemaType = 'WebPage',
        public array $faqs = [],
        public array $people = [],
        public ?array $article = null,
        public array $breadcrumbs = [],
        public float $sitemapPriority = 0.6,
        public ?array $review = null,
        public ?string $about = null,
    ) {}

    public function key(): string
    {
        return $this->type.':'.$this->id;
    }

    public function wordCount(): int
    {
        return str_word_count($this->bodyText);
    }
}
