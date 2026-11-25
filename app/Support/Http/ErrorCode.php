<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Stable machine-readable error codes (docs/API.md §4).
 *
 * Clients branch on these, never on the human message. Adding a case is
 * additive; renaming or removing one is a breaking API change. Modules add
 * their codes here in the phase that introduces them.
 */
enum ErrorCode: string
{
    // Transport and access
    case ValidationFailed = 'VALIDATION_FAILED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case RateLimited = 'RATE_LIMITED';
    case Conflict = 'CONFLICT';
    case ServerError = 'SERVER_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case InvalidStateTransition = 'INVALID_STATE_TRANSITION';

    // Accounts
    case AccountInactive = 'ACCOUNT_INACTIVE';
    case TwoFactorSetupRequired = 'TWO_FACTOR_SETUP_REQUIRED';
    case InvalidTwoFactorCode = 'INVALID_TWO_FACTOR_CODE';
    case InvitationInvalid = 'INVITATION_INVALID';

    public function status(): int
    {
        return match ($this) {
            self::Unauthenticated => 401,
            self::Forbidden, self::AccountInactive, self::TwoFactorSetupRequired => 403,
            self::NotFound => 404,
            self::InvitationInvalid => 410,
            self::Conflict, self::InvalidStateTransition => 409,
            self::RateLimited => 429,
            self::ServerError => 500,
            self::ServiceUnavailable => 503,
            default => 422,
        };
    }
}
