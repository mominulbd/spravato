<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * The public site posts forms server-to-server, so Laravel sees the
 * Next.js server's address, not the visitor's. When the request carries
 * the shared site token, the visitor's IP and user agent forwarded by the
 * site are used instead: for rate limits and Turnstile only, never stored.
 */
final class SiteClient
{
    public const string TOKEN_HEADER = 'X-Uptms-Site-Token';

    public const string IP_HEADER = 'X-Uptms-Client-Ip';

    public const string AGENT_HEADER = 'X-Uptms-Client-Agent';

    public static function trusted(Request $request): bool
    {
        $secret = config('services.frontend.site_token');
        $token = $request->header(self::TOKEN_HEADER);

        return is_string($secret) && $secret !== '' && is_string($token) && hash_equals($secret, $token);
    }

    public static function ip(Request $request): string
    {
        $forwarded = $request->header(self::IP_HEADER);

        if (self::trusted($request) && is_string($forwarded) && filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
            return $forwarded;
        }

        return (string) $request->ip();
    }

    public static function userAgent(Request $request): ?string
    {
        $forwarded = $request->header(self::AGENT_HEADER);

        if (self::trusted($request) && is_string($forwarded) && $forwarded !== '') {
            return mb_substr($forwarded, 0, 512);
        }

        return $request->userAgent();
    }
}
