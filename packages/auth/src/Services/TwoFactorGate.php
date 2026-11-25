<?php

declare(strict_types=1);

namespace Uptms\Auth\Services;

use Uptms\Settings\Services\Settings;
use Uptms\Users\Models\User;

/**
 * The site-wide two-factor switch (Settings → Security). While it is off,
 * sign-in skips the code step and no role forces set-up; secrets already
 * confirmed are kept, so turning it back on needs no new set-up.
 */
final readonly class TwoFactorGate
{
    public function __construct(private Settings $settings) {}

    public function isOn(): bool
    {
        return (bool) $this->settings->get('security.two_factor_login');
    }

    /** Sign-in must ask this user for a code. */
    public function challenges(User $user): bool
    {
        return $this->isOn() && $user->hasTwoFactorEnabled();
    }

    /** This user must set up 2FA before using the admin. */
    public function requires(User $user): bool
    {
        return $this->isOn() && $user->requiresTwoFactor();
    }
}
