<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/** "Best time to call" on the Contact form (optional). */
enum BestTime: string
{
    case Anytime = 'anytime';
    case Mornings = 'mornings';
    case Afternoons = 'afternoons';

    public function label(): string
    {
        return match ($this) {
            self::Anytime => 'Any time during office hours',
            self::Mornings => 'Weekday mornings',
            self::Afternoons => 'Weekday afternoons',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $time): array => ['value' => $time->value, 'label' => $time->label()], self::cases());
    }
}
