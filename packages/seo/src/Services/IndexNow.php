<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use Uptms\Seo\Models\SeoSetting;

/**
 * IndexNow (Bing, Yandex, Seznam…): tells search engines which URLs
 * changed. The key is public by design; the website serves it at
 * /indexnow-key.txt. Nothing is sent unless the site is live.
 */
class IndexNow
{
    private const string ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function __construct(private readonly SeoSettings $settings) {}

    public function enabled(): bool
    {
        return (bool) config('seo.live') && (bool) $this->settings->get('indexnow');
    }

    /**
     * The site's key: one stored by an earlier version, else derived from
     * APP_KEY, so reading it (the public robots endpoint) never writes.
     */
    public function key(): string
    {
        $key = SeoSetting::query()->where('key', 'indexnow_key')->value('value');

        if (is_string($key) && $key !== '') {
            return $key;
        }

        return substr(hash_hmac('sha256', 'uptms-indexnow', (string) config('app.key')), 0, 32);
    }

    /** @param list<string> $urls absolute */
    public function submit(array $urls): bool
    {
        if (! $this->enabled() || $urls === []) {
            return false;
        }

        $site = SchemaGraph::siteUrl();

        try {
            $response = Http::timeout(10)->asJson()->post(self::ENDPOINT, [
                'host' => parse_url($site, PHP_URL_HOST),
                'key' => $this->key(),
                'keyLocation' => $site.'/indexnow-key.txt',
                'urlList' => array_slice(array_values(array_unique($urls)), 0, 10000),
            ]);
        } catch (Throwable $e) {
            Log::warning('IndexNow submission failed.', ['exception' => $e::class]);

            return false;
        }

        return $response->successful();
    }
}
