<?php

declare(strict_types=1);

namespace Uptms\Users\Domain\Enums;

/**
 * Derived, never stored: an invited user has no password yet; a deactivated
 * user keeps their history but cannot sign in.
 */
enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Deactivated = 'deactivated';
}
