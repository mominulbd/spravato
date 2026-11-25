<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

/**
 * Turns what the browser sent into the coarse labels of Figma 05's "Where
 * it came from" card. Only labels are stored, never the raw user agent or
 * the full referring URL (which can carry search terms).
 */
final class SourceDescriber
{
    private const array SEARCH_ENGINES = [
        'google.' => 'Google',
        'bing.com' => 'Bing',
        'duckduckgo.com' => 'DuckDuckGo',
        'search.yahoo.' => 'Yahoo',
        'ecosia.org' => 'Ecosia',
    ];

    /** "Mobile · Safari" */
    public function device(?string $userAgent): ?string
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return null;
        }

        $ua = $userAgent;
        $kind = match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua) => 'Tablet',
            (bool) preg_match('/Mobi|iPhone|Android.+Mobile/i', $ua) => 'Mobile',
            default => 'Desktop',
        };
        $browser = match (true) {
            (bool) preg_match('/Edg\//', $ua) => 'Edge',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/', $ua) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/', $ua) => 'Chrome',
            (bool) preg_match('/Safari/', $ua) => 'Safari',
            default => null,
        };

        return $browser === null ? $kind : $kind.' · '.$browser;
    }

    /**
     * Host of an external referrer, or null for none / the site itself.
     */
    public function referrerHost(?string $referrer, ?string $siteHost): ?string
    {
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = mb_strtolower(preg_replace('/^www\./', '', $host) ?? $host);

        return $siteHost !== null && $host === mb_strtolower(preg_replace('/^www\./', '', $siteHost) ?? $siteHost) ? null : mb_substr($host, 0, 120);
    }

    /** Path of the site page visited just before, if the referrer is the site. */
    public function previousPage(?string $referrer, ?string $siteHost): ?string
    {
        if (! is_string($referrer) || $siteHost === null) {
            return null;
        }

        $parts = parse_url($referrer);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if (! is_string($host) || preg_replace('/^www\./', '', mb_strtolower($host)) !== preg_replace('/^www\./', '', mb_strtolower($siteHost))) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        return mb_substr($path === '' ? '/' : $path, 0, 190);
    }

    /**
     * "Google · organic", "Google · cpc", "facebook.com", "Direct".
     *
     * @param  array<string, string>|null  $utm
     */
    public function firstTouch(?string $referrerHost, ?array $utm): string
    {
        $source = $utm['source'] ?? null;
        $medium = $utm['medium'] ?? null;

        if (is_string($source) && $source !== '') {
            return ucfirst($source).(is_string($medium) && $medium !== '' ? ' · '.$medium : '');
        }

        if ($referrerHost === null) {
            return 'Direct';
        }

        foreach (self::SEARCH_ENGINES as $needle => $label) {
            if (str_contains($referrerHost, $needle)) {
                return $label.' · organic';
            }
        }

        return $referrerHost;
    }
}
