<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Illuminate\Support\Str;

/**
 * Keyed hashes that let staff find an encrypted request by name, phone or
 * email without storing those values in plaintext. Each searchable token
 * (a word of the name, the full phone number, its last four digits, the
 * email) becomes a 16-character HMAC in `search_index`; a search hashes
 * its own tokens the same way and requires all of them.
 */
final class BlindIndex
{
    private const int TOKEN_LENGTH = 16;

    /** Full HMAC of a normalised phone number, for the duplicate check. */
    public function phoneHash(string $phone): string
    {
        return $this->hmac('phone:'.$this->digits($phone));
    }

    public function build(string $name, string $phone, ?string $email): string
    {
        $tokens = [];

        foreach ($this->words($name) as $word) {
            $tokens[] = $this->token('name', $word);
        }

        $digits = $this->digits($phone);
        $tokens[] = $this->token('phone', $digits);
        $tokens[] = $this->token('last4', substr($digits, -4));

        if ($email !== null && $email !== '') {
            $tokens[] = $this->token('email', mb_strtolower(trim($email)));
        }

        return implode(' ', array_values(array_unique($tokens)));
    }

    /**
     * Tokens a search term must all match. Empty when the term can only
     * match the reference (handled by the caller).
     *
     * @return list<string>
     */
    public function query(string $search): array
    {
        $search = trim($search);

        if (str_contains($search, '@')) {
            return [$this->token('email', mb_strtolower($search))];
        }

        $digits = preg_replace('/\D/', '', $search) ?? '';

        if ($digits !== '' && preg_match('/^[\d\s()+.\-•]+$/u', $search) === 1) {
            return match (true) {
                strlen($digits) === 4 => [$this->token('last4', $digits)],
                strlen($digits) >= 10 => [$this->token('phone', $this->digits($digits))],
                default => [],
            };
        }

        return array_map(fn (string $word): string => $this->token('name', $word), $this->words($search));
    }

    /** US numbers: the last ten digits, so "+1 (253) 555-0198" = "2535550198". */
    private function digits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        $ascii = mb_strtolower(Str::ascii($text));
        $words = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($words));
    }

    private function token(string $kind, string $value): string
    {
        return substr($this->hmac($kind.':'.$value), 0, self::TOKEN_LENGTH);
    }

    private function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key());
    }

    private function key(): string
    {
        $configured = config('consultations.index_key');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // Derived, so the index key differs from the encryption key itself.
        return hash_hmac('sha256', 'uptms.consultations.blind-index', (string) config('app.key'));
    }
}
