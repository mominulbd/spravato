<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile check for the public form. Without a secret key
 * (local, tests) the check is off and the honeypot and rate limit remain.
 * A token Cloudflare rejects fails; Cloudflare being down does not, so an
 * outage never costs the clinic a real request.
 */
class Turnstile
{
    private const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function enabled(): bool
    {
        $secret = config('services.turnstile.secret_key');

        return is_string($secret) && $secret !== '';
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if ($token === null || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->retry(1, 200, throw: false)->post(self::VERIFY_URL, array_filter([
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (Throwable $e) {
            // Cloudflare unreachable: let the request through rather than lose
            // it; the honeypot and the rate limit still apply.
            Log::warning('Turnstile verification unavailable.', ['exception' => $e::class]);

            return true;
        }

        if (! $response->successful()) {
            Log::warning('Turnstile verification unavailable.', ['status' => $response->status()]);

            return true;
        }

        return $response->json('success') === true;
    }
}
