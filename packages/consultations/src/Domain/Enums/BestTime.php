<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/** "Best time to reach you" in the consultation dialog. */
enum BestTime: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case EarlyEvening = 'early_evening';
    case NoPreference = 'no_preference';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning',
            self::Afternoon => 'Afternoon',
            self::EarlyEvening => 'Early evening',
            self::NoPreference => 'No preference',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $time): array => ['value' => $time->value, 'label' => $time->label()], self::cases());
    }
}
