<?php

declare(strict_types=1);

namespace Uptms\Publishing\Services;

/**
 * Signed, 30-minute tokens that let the website render one record's working
 * copy (a post's "Preview"). The token names a kind and an id and expires;
 * nothing else about the admin session leaves the admin.
 */
final class SignedPreviews
{
    private const int TTL_SECONDS = 1800;

    public function issue(string $kind, int $id): string
    {
        $payload = $this->encode((string) json_encode(['k' => $kind, 'i' => $id, 'e' => now()->getTimestamp() + self::TTL_SECONDS]));

        return $payload.'.'.$this->sign($payload);
    }

    /** The id, or null when the token is forged, expired or for another kind. */
    public function verify(string $kind, string $token): ?int
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if ($payload === '' || ! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        if (! is_array($data) || ($data['k'] ?? null) !== $kind || ! is_int($data['i'] ?? null) || ! is_int($data['e'] ?? null) || $data['e'] < now()->getTimestamp()) {
            return null;
        }

        return $data['i'];
    }

    public static function ttl(): int
    {
        return self::TTL_SECONDS;
    }

    private function sign(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', 'preview|'.$payload, (string) config('app.key'), true));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
