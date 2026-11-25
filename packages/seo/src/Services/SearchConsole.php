<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google Search Console through a service account (no Google SDK): an
 * RS256 JWT is exchanged for an access token, then the Webmasters API
 * submits the sitemap and reads search analytics. Unconfigured, every
 * method answers "not connected" and nothing is sent.
 */
class SearchConsole
{
    private const string SCOPE = 'https://www.googleapis.com/auth/webmasters';

    private const string API = 'https://www.googleapis.com/webmasters/v3/sites/';

    private const string INSPECT = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    public function connected(): bool
    {
        return $this->credentials() !== null && $this->property() !== '';
    }

    /** "admin@uptms.com"-style account the integration acts as. */
    public function account(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    public function property(): string
    {
        return (string) config('seo.search_console.property');
    }

    public function submitSitemap(string $sitemapUrl): bool
    {
        if (! $this->connected() || ! config('seo.live')) {
            return false;
        }

        $response = $this->request('put', 'sitemaps/'.rawurlencode($sitemapUrl));

        return $response !== null;
    }

    /** When Google last downloaded the sitemap (Figma 22's "Read by Google"), checked every few hours. */
    public function sitemapReadAt(string $sitemapUrl): ?string
    {
        if (! $this->connected()) {
            return null;
        }

        /** @var string|null */
        return Cache::remember('gsc:sitemap-read:'.md5($sitemapUrl), now()->addHours(3), function () use ($sitemapUrl): ?string {
            $read = $this->request('get', 'sitemaps/'.rawurlencode($sitemapUrl))['lastDownloaded'] ?? null;

            return is_string($read) ? $read : null;
        });
    }

    /**
     * Search analytics rows for the last `$days` days.
     *
     * @param  list<string>  $dimensions  e.g. ['query'] or ['page']
     * @return list<array{keys: list<string>, clicks: float, impressions: float, ctr: float, position: float}>|null
     */
    public function analytics(int $days, array $dimensions = [], int $rows = 10, ?string $page = null): ?array
    {
        if (! $this->connected()) {
            return null;
        }

        $key = 'gsc:'.md5(json_encode([$days, $dimensions, $rows, $page]) ?: '');

        /** @var list<array{keys: list<string>, clicks: float, impressions: float, ctr: float, position: float}>|null */
        return Cache::remember($key, now()->addHours(6), function () use ($days, $dimensions, $rows, $page): ?array {
            $body = [
                'startDate' => now()->subDays($days + 2)->toDateString(),
                'endDate' => now()->subDays(2)->toDateString(),
                'dimensions' => $dimensions,
                'rowLimit' => $rows,
            ];

            if ($page !== null) {
                $body['dimensionFilterGroups'] = [['filters' => [['dimension' => 'page', 'operator' => 'equals', 'expression' => $page]]]];
            }

            $response = $this->request('post', 'searchAnalytics/query', $body);

            return $response === null ? null : array_values((array) ($response['rows'] ?? []));
        });
    }

    /**
     * Is the URL in Google's index? The URL Inspection API (2,000 a day per
     * property), so only the nightly audit asks. Null when unknown.
     */
    public function indexed(string $url): ?bool
    {
        if (! $this->connected()) {
            return null;
        }

        $verdict = $this->call('post', self::INSPECT, ['inspectionUrl' => $url, 'siteUrl' => $this->property()])['inspectionResult']['indexStatusResult']['verdict'] ?? null;

        return is_string($verdict) ? $verdict === 'PASS' : null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function request(string $method, string $path, array $body = []): ?array
    {
        return $this->call($method, self::API.rawurlencode($this->property()).'/'.$path, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    private function call(string $method, string $url, array $body = []): ?array
    {
        $token = $this->token();

        if ($token === null) {
            return null;
        }

        try {
            $response = Http::withToken($token)->timeout(15)->{$method}($url, $body);
        } catch (Throwable $e) {
            Log::warning('Search Console request failed.', ['exception' => $e::class]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Search Console refused a request.', ['status' => $response->status()]);

            return null;
        }

        /** @var array<string, mixed> */
        return $response->json() ?? [];
    }

    private function token(): ?string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
        }

        /** @var string|null */
        return Cache::remember('gsc:token', now()->addMinutes(50), function () use ($credentials): ?string {
            $now = time();
            $segment = fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');
            $unsigned = $segment(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$segment([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            if (! openssl_sign($unsigned, $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }

            try {
                $response = Http::asForm()->timeout(15)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '='),
                ]);
            } catch (Throwable) {
                return null;
            }

            $token = $response->json('access_token');

            return $response->successful() && is_string($token) ? $token : null;
        });
    }

    /** @return array{client_email: string, private_key: string, token_uri?: string}|null */
    private function credentials(): ?array
    {
        $raw = config('seo.search_console.credentials');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $json = str_starts_with(trim($raw), '{') ? $raw : (is_file($raw) ? (string) file_get_contents($raw) : '');
        $data = json_decode($json, true);

        return is_array($data) && is_string($data['client_email'] ?? null) && is_string($data['private_key'] ?? null) ? $data : null;
    }
}
