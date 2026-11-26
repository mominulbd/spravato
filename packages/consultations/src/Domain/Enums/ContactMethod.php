<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/** "Preferred follow-up" in the consultation dialog (optional). */
enum ContactMethod: string
{
    case Phone = 'phone';
    case Email = 'email';
    case Either = 'either';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::Either => 'Either',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $method): array => ['value' => $method->value, 'label' => $method->label()], self::cases());
    }
}
