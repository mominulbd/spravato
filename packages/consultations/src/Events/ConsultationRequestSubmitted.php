<?php

declare(strict_types=1);

namespace Uptms\Consultations\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Carries the id only: listeners load what they need, nothing personal travels on the queue. */
final readonly class ConsultationRequestSubmitted
{
    use Dispatchable;

    public function __construct(public int $requestId) {}
}
