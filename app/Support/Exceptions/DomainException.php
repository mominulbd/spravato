<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use App\Support\Http\ErrorCode;
use RuntimeException;

/**
 * Base class for every business-rule failure.
 *
 * A domain exception is expected control flow, not an incident: it maps to a
 * stable error code and carries structured context that becomes `errors` in
 * the response, so a client can point at the exact failing field or record.
 */
abstract class DomainException extends RuntimeException
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);

        $this->context = $context;
    }

    abstract public function errorCode(): ErrorCode;

    public function status(): int
    {
        return $this->errorCode()->status();
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
