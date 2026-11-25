<?php

declare(strict_types=1);

namespace Uptms\Publishing\Domain\Enums;

/**
 * Life cycle of a page or condition. Only Publish actions move an entry to
 * Published; the status is never mass-assigned.
 */
enum PublishStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Published],
            self::Scheduled => [self::Draft, self::Published, self::Scheduled],
            self::Published => [self::Draft, self::Published, self::Scheduled],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }
}
