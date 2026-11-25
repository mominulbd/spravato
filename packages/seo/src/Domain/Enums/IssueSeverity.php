<?php

declare(strict_types=1);

namespace Uptms\Seo\Domain\Enums;

/** The severity badge of Figma 19's issue list. */
enum IssueSeverity: string
{
    case Urgent = 'urgent';
    case Warning = 'warning';
    case Notice = 'notice';
    case Passed = 'passed';

    /** Points the site score loses while an issue of this kind is open. */
    public function penalty(): int
    {
        return match ($this) {
            self::Urgent => 5,
            self::Warning => 2,
            self::Notice => 1,
            self::Passed => 0,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Urgent => 0,
            self::Warning => 1,
            self::Notice => 2,
            self::Passed => 3,
        };
    }
}
