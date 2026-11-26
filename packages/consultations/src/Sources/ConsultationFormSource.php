<?php

declare(strict_types=1);

namespace Uptms\Consultations\Sources;

use Uptms\Consultations\Domain\Enums\BestTime;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Domain\Enums\ContactMethod;
use Uptms\Consultations\Domain\Enums\PatientStatus;
use Uptms\Publishing\Contracts\BlockSource;

/**
 * The choices of the website's consultation dialog, so what a person picks
 * is always a value the inbox understands. When this module is disabled
 * the source is missing and the site shows the phone number instead.
 */
final class ConsultationFormSource implements BlockSource
{
    public function key(): string
    {
        return 'consultation-form';
    }

    public function resolve(array $params): mixed
    {
        return [
            'accepting' => true,
            'topics' => ConsultationTopic::options(),
            'best_times' => BestTime::options(),
            'contact_methods' => ContactMethod::options(),
            'patient_statuses' => PatientStatus::options(),
        ];
    }

    public function options(): array
    {
        return [];
    }
}
