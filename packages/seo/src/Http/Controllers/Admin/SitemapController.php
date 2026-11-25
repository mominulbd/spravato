<?php

declare(strict_types=1);

namespace Uptms\Seo\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Seo\Models\SeoSetting;
use Uptms\Seo\Services\IndexNow;
use Uptms\Seo\Services\RobotsTxt;
use Uptms\Seo\Services\SchemaGraph;
use Uptms\Seo\Services\SearchConsole;
use Uptms\Seo\Services\SeoSettings;
use Uptms\Seo\Services\SitemapBuilder;

/** Figma 22. Changes are saved through PUT seo-settings/sitemap. */
class SitemapController
{
    public function show(SitemapBuilder $sitemap, SeoSettings $settings, SearchConsole $console, IndexNow $indexNow): JsonResponse
    {
        $changed = AuditLog::query()->with('user')->where('action', 'seo.settings_updated')->where('auditable_label', 'sitemap')->whereNotNull('new_values->robots_txt')->latest('id')->first();
        $notified = SeoSetting::query()->where('key', 'notified_at')->value('value');
        $url = SchemaGraph::siteUrl().'/sitemap.xml';
        // The sitemap is rebuilt from published content, so it changed when the latest entry did.
        $entries = $sitemap->entries();
        $dates = array_filter(array_column($entries, 'lastmod'));

        return ApiResponse::success([
            'groups' => $sitemap->groups(),
            'sitemap_url' => $url,
            'sitemap' => [
                'url' => $url,
                'urls' => count($entries),
                'updated_at' => $dates === [] ? null : max($dates),
                'read_by_google_at' => $console->sitemapReadAt($url),
            ],
            'robots' => [
                'text' => $settings->get('robots_txt'),
                'default' => SeoSettings::DEFAULT_ROBOTS,
                'changed_at' => $changed?->created_at->toIso8601String(),
                'changed_by' => $changed?->user?->getAttribute('name'),
            ],
            'notify' => [
                'live' => (bool) config('seo.live'),
                'indexnow' => (bool) $settings->get('indexnow'),
                'indexnow_ready' => $indexNow->enabled(),
                'search_console_submit' => (bool) $settings->get('search_console_submit'),
                'search_console' => ['connected' => $console->connected(), 'account' => $console->account(), 'property' => $console->property() ?: null],
                'include_lastmod' => (bool) $settings->get('include_lastmod'),
                'last_notified_at' => is_string($notified) ? $notified : null,
            ],
        ]);
    }

    /** "Test a URL": would Googlebot be allowed? Tests the given text, or the saved one. */
    public function test(Request $request, RobotsTxt $robots, SeoSettings $settings): JsonResponse
    {
        $validated = $request->validate(['path' => ['required', 'string', 'max:500'], 'content' => ['nullable', 'string', 'max:5000']]);

        return ApiResponse::success($robots->test($validated['content'] ?? (string) $settings->get('robots_txt'), $validated['path']));
    }
}
