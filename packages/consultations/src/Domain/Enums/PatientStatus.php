<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/** "Patient status" in the consultation dialog (optional): is the person already seen at the clinic? */
enum PatientStatus: string
{
    case New = 'new';
    case Existing = 'existing';
    case NotSure = 'not_sure';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New patient',
            self::Existing => 'Existing patient',
            self::NotSure => 'Not sure',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $status): array => ['value' => $status->value, 'label' => $status->label()], self::cases());
    }
}
