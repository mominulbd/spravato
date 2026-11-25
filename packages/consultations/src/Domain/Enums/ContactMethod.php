<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/** "Preferred method of communication" on the qualification form. */
enum ContactMethod: string
{
    case Phone = 'phone';
    case Text = 'text';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone call',
            self::Text => 'Text message',
            self::Email => 'Email',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $method): array => ['value' => $method->value, 'label' => $method->label()], self::cases());
    }
}
