<?php

declare(strict_types=1);

namespace Uptms\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Uptms\Seo\Domain\SeoSubject;

/**
 * Implemented by each content module (pages, posts, conditions) and
 * registered with SeoSources under its morph alias. The SEO module asks a
 * source; it never queries the content tables itself.
 */
interface SeoSource
{
    /** The morph alias, e.g. `page`. */
    public function type(): string;

    /** "Pages", "Posts & resources" … */
    public function label(): string;

    /** The record seo_meta is attached to, or null when it doesn't exist. */
    public function model(int $id): ?Model;

    /**
     * One subject. `$live` describes the published version; otherwise the
     * working copy (what an editor is looking at).
     */
    public function subject(int $id, bool $live = false): ?SeoSubject;

    /**
     * Every published subject, for the sitemap, the site audit and link suggestions.
     *
     * @return iterable<SeoSubject>
     */
    public function published(): iterable;
}
