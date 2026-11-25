<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Http\ErrorCode;

/**
 * A domain exception whose code is chosen at the throw site. Modules define
 * dedicated classes for rules that are thrown from more than one place.
 */
final class GenericDomainException extends DomainException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly ErrorCode $errorCode,
        string $message,
        array $context = [],
    ) {
        parent::__construct($message, $context);
    }

    public function errorCode(): ErrorCode
    {
        return $this->errorCode;
    }
}
