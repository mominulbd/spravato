<?php

declare(strict_types=1);

namespace Uptms\Auth\Services;

use InvalidArgumentException;

/**
 * RFC 6238 time-based one-time passwords (SHA-1, 6 digits, 30-second steps),
 * the variant every authenticator app supports. Framework-free so it can be
 * unit tested against the RFC vectors.
 */
final class Totp
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const int PERIOD = 30;

    public const int DIGITS = 6;

    /** A new 160-bit secret, base32 encoded (32 characters). */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function code(string $secret, int $timestep): string
    {
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $timestep), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function timestep(?int $unixTime = null): int
    {
        return intdiv($unixTime ?? time(), self::PERIOD);
    }

    /**
     * The matching timestep within ±$window steps of now, or null. Callers
     * reject a timestep at or before the last one used, so a code cannot be
     * replayed.
     */
    public function verify(string $secret, string $code, int $window = 1, ?int $unixTime = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        $now = $this->timestep($unixTime);

        for ($step = $now - $window; $step <= $now + $window; $step++) {
            if (hash_equals($this->code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account)
            .'?'.http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => self::DIGITS,
                'period' => self::PERIOD,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    public function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return $output;
    }

    public function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim(str_replace(' ', '', $secret), '='));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index === false) {
                throw new InvalidArgumentException('The secret is not valid base32.');
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr((int) bindec($byte));
            }
        }

        return $bytes;
    }
}
