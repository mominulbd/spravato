<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/** Which website form sent the request. */
enum ConsultationOrigin: string
{
    case Form = 'form';
    case Quiz = 'quiz';

    public function label(): string
    {
        return match ($this) {
            self::Form => 'Consultation form',
            self::Quiz => 'Self-check',
        };
    }
}
