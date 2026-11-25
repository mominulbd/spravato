<?php

declare(strict_types=1);

namespace Uptms\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Uptms\AuditLog\Concerns\Auditable;

/**
 * @property int $id
 * @property string $seoable_type
 * @property int $seoable_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $focus_keyphrase
 * @property list<string>|null $keyphrases
 * @property string|null $canonical_url
 * @property bool $robots_index
 * @property bool $robots_follow
 * @property bool $in_sitemap
 * @property string|null $breadcrumb_title
 * @property string $max_image_preview
 * @property int|null $max_snippet
 * @property bool $og_custom
 * @property string|null $og_title
 * @property string|null $og_description
 * @property int|null $og_image_id
 * @property string $twitter_card
 * @property string|null $schema_type
 * @property string|null $schema_about
 * @property list<string>|null $schema_disabled
 * @property array<string, mixed>|list<array<string, mixed>>|null $schema_custom
 * @property int|null $reviewed_by
 * @property Carbon|null $last_reviewed_at
 * @property list<array{label: string, url: string}>|null $citations
 * @property int|null $seo_score
 * @property int|null $readability_score
 * @property array<string, mixed>|null $analysis
 * @property Carbon|null $analyzed_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeoMeta extends Model
{
    use Auditable;

    protected $table = 'seo_meta';

    /** Editable from the SEO, Social and Schema tabs. Scores are computed, never posted. */
    public const array EDITABLE = [
        'meta_title', 'meta_description', 'focus_keyphrase', 'keyphrases', 'canonical_url',
        'robots_index', 'robots_follow', 'in_sitemap', 'breadcrumb_title', 'max_image_preview', 'max_snippet',
        'og_custom', 'og_title', 'og_description', 'og_image_id', 'twitter_card',
        'schema_type', 'schema_about', 'schema_disabled', 'schema_custom',
        'reviewed_by', 'last_reviewed_at', 'citations',
    ];

    protected $fillable = self::EDITABLE;

    /** @var list<string> */
    protected array $auditExclude = ['analysis', 'analyzed_at', 'seo_score', 'readability_score', 'updated_by'];

    protected function casts(): array
    {
        return [
            'keyphrases' => 'array',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
            'in_sitemap' => 'boolean',
            'max_snippet' => 'integer',
            'og_custom' => 'boolean',
            'og_image_id' => 'integer',
            'schema_disabled' => 'array',
            'schema_custom' => 'array',
            'reviewed_by' => 'integer',
            'last_reviewed_at' => 'date',
            'citations' => 'array',
            'seo_score' => 'integer',
            'readability_score' => 'integer',
            'analysis' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function auditActionPrefix(): string
    {
        return 'seo';
    }

    /** Defaults for a subject with no row yet. */
    public static function blank(string $type, int $id): self
    {
        $meta = new self([
            'robots_index' => true, 'robots_follow' => true, 'in_sitemap' => true,
            'max_image_preview' => 'large', 'og_custom' => false, 'twitter_card' => 'summary_large_image',
        ]);
        $meta->seoable_type = $type;
        $meta->seoable_id = $id;

        return $meta;
    }
}
