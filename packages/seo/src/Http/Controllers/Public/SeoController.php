<?php

declare(strict_types=1);

namespace Uptms\Seo\Http\Controllers\Public;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Uptms\Publishing\Services\PublicCache;
use Uptms\Seo\Services\SeoSettings;

/** Site-wide <head> values the website's root layout needs. */
class SeoController
{
    public function site(SeoSettings $settings, PublicCache $cache): JsonResponse
    {
        return ApiResponse::success($cache->remember('seo:site', fn (): array => [
            'site_name' => $settings->get('site_name'),
            'separator' => $settings->get('separator'),
            'x_handle' => $settings->get('x_handle') ?: null,
            'verification' => array_filter(['google' => $settings->get('google') ?: null, 'bing' => $settings->get('bing') ?: null]),
            // null while analytics are off, so the site renders no third-party script at all.
            'tag_manager' => $settings->tagManager(),
        ]));
    }
}
