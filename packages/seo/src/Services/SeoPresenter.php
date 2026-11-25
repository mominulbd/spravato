<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Str;
use Uptms\Media\Services\MediaPresenter;
use Uptms\Seo\Domain\SeoSubject;
use Uptms\Seo\Models\SeoMeta;

/**
 * Resolves what search engines and social apps see for a subject: saved
 * values where staff set them, otherwise defaults from the content and the
 * title templates. The website renders `public()` as <head> tags and JSON-LD.
 */
final readonly class SeoPresenter
{
    public function __construct(
        private TitleTemplate $titles,
        private SeoSettings $settings,
        private MediaPresenter $media,
        private SchemaGraph $schema,
    ) {}

    public function meta(SeoSubject $subject): SeoMeta
    {
        return SeoMeta::query()->where('seoable_type', $subject->type)->where('seoable_id', $subject->id)->first()
            ?? SeoMeta::blank($subject->type, $subject->id);
    }

    /** The title Google shows: a saved title may use the template variables too. */
    public function title(SeoSubject $subject, SeoMeta $meta): string
    {
        $vars = ['title' => $subject->title, ...$subject->templateVars];

        return $meta->meta_title !== null && trim($meta->meta_title) !== ''
            ? $this->titles->render($meta->meta_title, $vars)
            : $this->titles->for($subject->templateKey, $subject->title, $subject->templateVars);
    }

    public function defaultDescription(SeoSubject $subject): string
    {
        $source = $subject->description !== null && trim($subject->description) !== '' ? $subject->description : $subject->firstParagraph;

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', strip_tags($source))), 155, '…', preserveWords: true);
    }

    public function description(SeoSubject $subject, SeoMeta $meta): string
    {
        return $meta->meta_description !== null && trim($meta->meta_description) !== '' ? trim($meta->meta_description) : $this->defaultDescription($subject);
    }

    public function canonical(SeoSubject $subject, SeoMeta $meta): string
    {
        return $meta->canonical_url !== null && $meta->canonical_url !== '' ? $meta->canonical_url : SchemaGraph::absolute($subject->path);
    }

    /** @return array{url: string, width: int|null, height: int|null, alt: string}|null */
    public function shareImage(SeoSubject $subject, SeoMeta $meta): ?array
    {
        $default = $this->settings->get('default_og_image_id');

        foreach ([$meta->og_image_id, $subject->imageId, is_int($default) ? $default : null] as $id) {
            $image = $this->media->present($id);

            if ($image !== null) {
                return ['url' => $image['url'], 'width' => $image['width'], 'height' => $image['height'], 'alt' => $image['alt']];
            }
        }

        return null;
    }

    /**
     * Everything the website puts in <head> for this subject.
     *
     * @return array<string, mixed>
     */
    public function public(SeoSubject $subject, ?SeoMeta $meta = null): array
    {
        $meta ??= $this->meta($subject);
        $title = $this->title($subject, $meta);
        $description = $this->description($subject, $meta);
        $image = $this->shareImage($subject, $meta);
        $handle = ltrim((string) $this->settings->get('x_handle'), '@');
        $ogTitle = $meta->og_custom && $meta->og_title ? $meta->og_title : $title;
        $ogDescription = $meta->og_custom && $meta->og_description ? $meta->og_description : $description;

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $this->canonical($subject, $meta),
            'robots' => [
                'index' => $meta->robots_index,
                'follow' => $meta->robots_follow,
                'max_image_preview' => $meta->max_image_preview,
                'max_snippet' => $meta->max_snippet,
            ],
            'open_graph' => [
                'type' => $subject->article !== null ? 'article' : 'website',
                'title' => $ogTitle,
                'description' => $ogDescription,
                'url' => SchemaGraph::absolute($subject->path),
                'site_name' => (string) $this->settings->get('site_name'),
                'image' => $image,
                'published_time' => $subject->article !== null ? $subject->publishedAt?->toIso8601String() : null,
                'modified_time' => $subject->article !== null ? $subject->updatedAt?->toIso8601String() : null,
            ],
            'twitter' => [
                'card' => $meta->twitter_card,
                'site' => $handle !== '' ? '@'.$handle : null,
            ],
            'json_ld' => $this->schema->build($subject, $meta, $title, $description, $image['url'] ?? null)['graph'],
        ];
    }
}
