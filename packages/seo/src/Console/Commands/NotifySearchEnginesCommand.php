<?php

declare(strict_types=1);

namespace Uptms\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Uptms\Seo\Models\SeoSetting;
use Uptms\Seo\Services\IndexNow;
use Uptms\Seo\Services\SchemaGraph;
use Uptms\Seo\Services\SearchConsole;
use Uptms\Seo\Services\SeoSettings;
use Uptms\Seo\Services\SitemapBuilder;

/**
 * Every 10 minutes: URLs changed since the last run go to IndexNow, and the
 * sitemap is (re)submitted to Search Console when anything changed. Off
 * unless SEO_LIVE=true.
 */
final class NotifySearchEnginesCommand extends Command
{
    protected $signature = 'seo:notify';

    protected $description = 'Tell search engines about changed URLs (IndexNow, Search Console)';

    public function handle(SitemapBuilder $sitemap, IndexNow $indexNow, SearchConsole $console, SeoSettings $settings): int
    {
        if (! config('seo.live')) {
            $this->info('SEO_LIVE is off; nothing sent.');

            return self::SUCCESS;
        }

        $since = SeoSetting::query()->where('key', 'notified_at')->value('value');
        $since = is_string($since) ? Carbon::parse($since) : now()->subDay();
        $changed = array_values(array_filter($sitemap->entries(), fn (array $e): bool => $e['lastmod'] === null || Carbon::parse($e['lastmod'])->gt($since)));
        $urls = array_map(fn (array $e): string => SchemaGraph::absolute($e['path']), $changed);

        if ($urls !== []) {
            $indexNow->submit($urls);

            if ((bool) $settings->get('search_console_submit')) {
                $console->submitSitemap(SchemaGraph::siteUrl().'/sitemap.xml');
            }
        }

        SeoSetting::query()->updateOrCreate(['key' => 'notified_at'], ['value' => now()->toIso8601String()]);
        $this->info(count($urls).' changed URLs.');

        return self::SUCCESS;
    }
}
