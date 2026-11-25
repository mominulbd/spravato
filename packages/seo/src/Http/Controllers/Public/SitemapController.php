<?php

declare(strict_types=1);

namespace Uptms\Seo\Http\Controllers\Public;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Uptms\Publishing\Services\PublicCache;
use Uptms\Seo\Services\IndexNow;
use Uptms\Seo\Services\SeoSettings;
use Uptms\Seo\Services\SitemapBuilder;

/** What the website's /sitemap.xml, /robots.txt and /indexnow-key.txt serve. */
class SitemapController
{
    public function sitemap(SitemapBuilder $sitemap, PublicCache $cache): JsonResponse
    {
        return ApiResponse::success($cache->remember('seo:sitemap', fn (): array => $sitemap->entries()));
    }

    public function robots(SeoSettings $settings, IndexNow $indexNow, PublicCache $cache): JsonResponse
    {
        return ApiResponse::success($cache->remember('seo:robots', fn (): array => [
            'text' => (string) $settings->get('robots_txt'),
            'indexnow_key' => $indexNow->enabled() ? $indexNow->key() : null,
        ]));
    }
}
