<?php

declare(strict_types=1);

namespace Uptms\Pages\Services;

/**
 * Signed, short-lived tokens that let the website render a page's working
 * copy (Figma 07 "Preview"). The token names one page and expires; nothing
 * else about the session leaves the admin.
 */
final class PreviewTokens
{
    private const int TTL_SECONDS = 1800;

    public function issue(int $pageId): string
    {
        $payload = $this->encode((string) json_encode(['p' => $pageId, 'e' => now()->getTimestamp() + self::TTL_SECONDS]));

        return $payload.'.'.$this->sign($payload);
    }

    /** The page id, or null when the token is forged or expired. */
    public function verify(string $token): ?int
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if ($payload === '' || ! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        if (! is_array($data) || ! is_int($data['p'] ?? null) || ! is_int($data['e'] ?? null) || $data['e'] < now()->getTimestamp()) {
            return null;
        }

        return $data['p'];
    }

    private function sign(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', 'page-preview|'.$payload, (string) config('app.key'), true));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
