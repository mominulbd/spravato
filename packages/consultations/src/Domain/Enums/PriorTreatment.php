<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/**
 * "Have you tried therapy and antidepressants?" on the qualification form.
 * Health information: stored encrypted as the value, shown as the label.
 */
enum PriorTreatment: string
{
    case Both = 'both';
    case TherapyOnly = 'therapy_only';
    case MedicationOnly = 'medication_only';
    case Neither = 'neither';

    public function label(): string
    {
        return match ($this) {
            self::Both => 'Yes, both therapy and antidepressants',
            self::TherapyOnly => 'Therapy only',
            self::MedicationOnly => 'Antidepressants only',
            self::Neither => 'Neither yet',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $treatment): array => ['value' => $treatment->value, 'label' => $treatment->label()], self::cases());
    }
}
