<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Http\ErrorCode;
use BackedEnum;

/**
 * Thrown by every state machine (consultation requests, page publishing,
 * clinical review). Names what was allowed so the client can render it.
 */
final class InvalidStateTransitionException extends DomainException
{
    /**
     * @param  list<BackedEnum>  $allowed
     */
    public static function make(string $subject, BackedEnum $from, BackedEnum $to, array $allowed): self
    {
        $allowedValues = array_map(fn (BackedEnum $case): string|int => $case->value, $allowed);

        $message = $allowedValues === []
            ? sprintf('%s is %s, which is final; no further changes are allowed.', $subject, $from->value)
            : sprintf('%s cannot go from %s to %s. Allowed: %s.', $subject, $from->value, $to->value, implode(', ', $allowedValues));

        return new self($message, [
            'from' => $from->value,
            'to' => $to->value,
            'allowed' => $allowedValues,
        ]);
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::InvalidStateTransition;
    }
}
