<?php

declare(strict_types=1);

namespace Uptms\Auth\Services;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Users\Models\User;

/**
 * Set-up, confirmation and checking of TOTP codes and single-use recovery
 * codes. The secret is stored with an encrypted cast; recovery codes are
 * stored as sha256 hashes inside an encrypted JSON list.
 */
final class TwoFactorService
{
    public const int RECOVERY_CODES = 8;

    public function __construct(
        private readonly Totp $totp,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Start set-up: a fresh, unconfirmed secret.
     *
     * @return array{secret: string, otpauth_uri: string}
     */
    public function begin(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new GenericDomainException(ErrorCode::Conflict, 'Two-factor authentication is already on.');
        }

        $secret = $this->totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->saveQuietly();

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->totp->uri($secret, $user->email, (string) config('app.name', 'UPTMS')),
        ];
    }

    /**
     * Finish set-up with the first code from the app.
     *
     * @return list<string> recovery codes, shown once
     */
    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactorEnabled() || $user->two_factor_secret === null) {
            throw new GenericDomainException(ErrorCode::Conflict, 'Start two-factor set-up first.');
        }

        if (! $this->checkCode($user, $code)) {
            throw new GenericDomainException(ErrorCode::InvalidTwoFactorCode, 'That code did not match. Check the time on your phone and try the newest code.', [
                'code' => ['That code did not match.'],
            ]);
        }

        $codes = $this->storeRecoveryCodes($user, confirm: true);
        $this->audit->record('auth.two_factor_enabled', $user, userId: $user->id);

        return $codes;
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->saveQuietly();

        $this->audit->record('auth.two_factor_disabled', $user, userId: $user->id);
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->storeRecoveryCodes($user, confirm: false);
        $this->audit->record('auth.recovery_codes_regenerated', $user, userId: $user->id);

        return $codes;
    }

    /** A current TOTP code that has not been used before. */
    public function checkCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        $step = $this->totp->verify($user->two_factor_secret, $code);
        $key = 'auth:totp-last-step:'.$user->id;

        if ($step === null || $step <= (int) Cache::get($key, 0)) {
            return false;
        }

        Cache::put($key, $step, now()->addMinutes(5));

        return true;
    }

    /** Consume a recovery code; each works once. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hashes = $this->recoveryHashes($user);
        $hash = hash('sha256', strtolower(trim($code)));
        $index = array_search($hash, $hashes, true);

        if ($index === false) {
            return false;
        }

        unset($hashes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($hashes))])->saveQuietly();

        return true;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($this->recoveryHashes($user));
    }

    /** @return list<string> */
    private function storeRecoveryCodes(User $user, bool $confirm): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $codes[] = strtolower(Str::random(5).'-'.Str::random(5));
        }

        $user->forceFill(array_filter([
            'two_factor_recovery_codes' => json_encode(array_map(fn (string $c): string => hash('sha256', $c), $codes)),
            'two_factor_confirmed_at' => $confirm ? now() : null,
        ]))->saveQuietly();

        return $codes;
    }

    /** @return list<string> */
    private function recoveryHashes(User $user): array
    {
        $decoded = json_decode((string) $user->two_factor_recovery_codes, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
