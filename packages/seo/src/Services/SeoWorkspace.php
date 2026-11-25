<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Seo\Domain\SeoSubject;
use Uptms\Seo\Models\SeoMeta;

/**
 * The SEO, Social and Schema tabs (Figma 08–10) for any subject: what the
 * editor loads, the live checklist while typing, and saving. Saved values
 * are live on the website at once (seo.edit), like Clinic settings.
 */
final readonly class SeoWorkspace
{
    public function __construct(
        private SeoSources $sources,
        private SeoPresenter $presenter,
        private SeoAnalyzer $analyzer,
        private SchemaGraph $schema,
        private TitleTemplate $titles,
        private SeoSettings $settings,
        private Reviewers $reviewers,
    ) {}

    public function subject(string $type, int $id): SeoSubject
    {
        $subject = $this->sources->get($type)?->subject($id);

        if ($subject === null) {
            throw new NotFoundHttpException('That page doesn’t exist.');
        }

        return $subject;
    }

    /** @return array<string, mixed> */
    public function payload(string $type, int $id, ?SeoMeta $draft = null): array
    {
        $subject = $this->subject($type, $id);
        $meta = $draft ?? $this->presenter->meta($subject);
        $title = $this->presenter->title($subject, $meta);
        $description = $this->presenter->description($subject, $meta);
        $image = $this->presenter->shareImage($subject, $meta);
        $analysis = $this->analysis($subject, $meta);
        $schema = $this->schema->build($subject, $meta, $title, $description, $image['url'] ?? null);

        return [
            'subject' => [
                'type' => $subject->type,
                'id' => $subject->id,
                'label' => $subject->label,
                'title' => $subject->title,
                'path' => $subject->path,
                'url' => SchemaGraph::absolute($subject->path),
                'is_home' => $subject->isHome,
                'published' => $subject->published,
                'template' => (string) ($this->settings->get('template_'.$subject->templateKey) ?? '%title% %sep% %site%'),
                'template_key' => $subject->templateKey,
                'default_schema_type' => $subject->defaultSchemaType,
            ],
            'meta' => $this->values($meta),
            'resolved' => [
                'title' => $title,
                'description' => $description,
                'default_title' => $this->titles->for($subject->templateKey, $subject->title, $subject->templateVars),
                'default_description' => $this->presenter->defaultDescription($subject),
                'canonical' => $this->presenter->canonical($subject, $meta),
                'share_title' => $meta->og_custom && $meta->og_title ? $meta->og_title : $title,
                'share_description' => $meta->og_custom && $meta->og_description ? $meta->og_description : $description,
                'share_image' => $image,
                'site_name' => (string) $this->settings->get('site_name'),
                'site_host' => (string) parse_url(SchemaGraph::siteUrl(), PHP_URL_HOST),
                'x_handle' => (string) $this->settings->get('x_handle'),
            ],
            'analysis' => $analysis,
            'schema' => $schema,
            'links' => $this->links($subject, $meta->focus_keyphrase),
            'page_types' => SchemaGraph::PAGE_TYPES,
            'reviewers' => $this->reviewers->all(),
            'analyzed_at' => $meta->analyzed_at?->toIso8601String(),
        ];
    }

    /**
     * Live checklist for unsaved values (nothing is stored).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function preview(string $type, int $id, array $values): array
    {
        $subject = $this->subject($type, $id);
        $meta = $this->presenter->meta($subject)->replicate();
        $meta->fill($values);

        return $this->payload($type, $id, $meta);
    }

    /**
     * @param  array<string, mixed>  $values  validated
     * @return array<string, mixed>
     */
    public function save(string $type, int $id, array $values, ?int $actorId): array
    {
        $subject = $this->subject($type, $id);

        DB::transaction(function () use ($subject, $values, $actorId): void {
            $meta = $this->presenter->meta($subject);
            $meta->fill($values);
            $analysis = $this->analysis($subject, $meta);
            $meta->forceFill([
                'seo_score' => $analysis['score'],
                'readability_score' => $analysis['readability'],
                'analysis' => ['seo' => $analysis['seo'], 'readability' => $analysis['readability_checks']],
                'analyzed_at' => now(),
                'updated_by' => $actorId,
            ])->save();
        });

        ContentPublished::dispatch(['content', 'seo']);

        return $this->payload($type, $id);
    }

    /**
     * Re-score without changing anything, e.g. after content was edited
     * (content modules call this on "Save draft" so list scores stay current).
     */
    public function rescore(SeoSubject $subject): int
    {
        $meta = $this->presenter->meta($subject);
        $analysis = $this->analysis($subject, $meta);
        $meta->forceFill([
            'seo_score' => $analysis['score'],
            'readability_score' => $analysis['readability'],
            'analysis' => ['seo' => $analysis['seo'], 'readability' => $analysis['readability_checks']],
            'analyzed_at' => now(),
        ])->saveQuietly();

        return $analysis['score'];
    }

    /**
     * Saved scores for list screens, keyed by subject id. Content never
     * scored (seeded, or not edited since SEO arrived) is scored once here,
     * so a list never shows a blank for a page nobody has opened.
     *
     * @param  list<int>  $ids
     * @return array<int, int|null>
     */
    public function scores(string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, int|null> $scores */
        $scores = SeoMeta::query()->where('seoable_type', $type)->whereIn('seoable_id', $ids)->pluck('seo_score', 'seoable_id')->all();
        $source = $this->sources->get($type);

        foreach ($ids as $id) {
            if (($scores[$id] ?? null) === null && $source !== null) {
                $subject = $source->subject($id);
                $scores[$id] = $subject === null ? null : $this->rescore($subject);
            }
        }

        return $scores;
    }

    /** @return array{score: int, readability: int, seo: list<array{key: string, status: string, text: string}>, readability_checks: list<array{key: string, status: string, text: string}>, title: array{chars: int, px: int, max_px: int}, description: array{chars: int, px: int, max_px: int}, keyphrase_uses: int} */
    public function analysis(SeoSubject $subject, SeoMeta $meta): array
    {
        $elsewhere = SeoMeta::query()
            ->whereNotNull('focus_keyphrase')
            ->where(fn ($q) => $q->where('seoable_type', '!=', $subject->type)->orWhere('seoable_id', '!=', $subject->id))
            ->pluck('focus_keyphrase')
            ->all();

        return $this->analyzer->analyze($subject, [
            'title' => $this->presenter->title($subject, $meta),
            'description' => $this->presenter->description($subject, $meta),
            'keyphrase' => $meta->focus_keyphrase,
            'related' => $meta->keyphrases ?? [],
            'index' => $meta->robots_index,
        ], [
            'focus_elsewhere' => array_values(array_filter($elsewhere, 'is_string')),
            'incoming_links' => $this->incoming($subject),
        ]);
    }

    /**
     * Pages that link here, and published pages worth linking to from here.
     *
     * @return array{incoming: list<array{label: string, path: string}>, suggestions: list<array{title: string, path: string, reason: string}>}
     */
    private function links(SeoSubject $subject, ?string $keyphrase): array
    {
        $all = $this->sources->allPublished();
        $incoming = [];

        foreach ($all as $other) {
            if ($other->key() !== $subject->key() && in_array($subject->path, array_map(self::pathOf(...), $other->links), true)) {
                $incoming[] = ['label' => $other->title, 'path' => $other->path];
            }
        }

        $linked = array_map(self::pathOf(...), $subject->links);
        $words = array_filter(preg_split('/\W+/', mb_strtolower($keyphrase ?? $subject->title)) ?: [], fn (string $w): bool => strlen($w) > 3);
        $suggestions = [];

        foreach ($all as $other) {
            if ($other->key() === $subject->key() || $other->isHome || in_array($other->path, $linked, true)) {
                continue;
            }

            $haystack = mb_strtolower($other->title.' '.$other->path);
            $hits = count(array_filter($words, fn (string $w): bool => str_contains($haystack, $w)));

            if ($hits > 0) {
                $suggestions[] = ['title' => $other->title, 'path' => $other->path, 'reason' => 'Shares '.($hits === 1 ? 'a word' : $hits.' words').' with the keyphrase', 'hits' => $hits];
            }
        }

        usort($suggestions, fn (array $a, array $b): int => $b['hits'] <=> $a['hits']);

        return [
            'incoming' => $incoming,
            'suggestions' => array_map(fn (array $s): array => ['title' => $s['title'], 'path' => $s['path'], 'reason' => $s['reason']], array_slice($suggestions, 0, 3)),
        ];
    }

    private function incoming(SeoSubject $subject): int
    {
        $count = 0;

        foreach ($this->sources->allPublished() as $other) {
            if ($other->key() !== $subject->key() && in_array($subject->path, array_map(self::pathOf(...), $other->links), true)) {
                $count++;
            }
        }

        return $count;
    }

    /** "/tms#how-it-works" → "/tms"; absolute links to the site count as internal. */
    public static function pathOf(string $href): string
    {
        $site = SchemaGraph::siteUrl();

        if ($site !== '' && str_starts_with($href, $site)) {
            $href = substr($href, strlen($site)) ?: '/';
        }

        $path = (string) strtok($href, '#?');

        return $path === '' ? $href : (rtrim($path, '/') ?: '/');
    }

    /** @return array<string, mixed> */
    private function values(SeoMeta $meta): array
    {
        $values = [];

        foreach (SeoMeta::EDITABLE as $key) {
            $values[$key] = $meta->getAttribute($key);
        }

        $values['last_reviewed_at'] = $meta->last_reviewed_at?->toDateString();

        return $values;
    }
}
