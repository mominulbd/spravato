<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/**
 * The call-back workflow (Figma 04–05). Only TransitionConsultationStatus
 * changes a request's status; the client never sets it directly. "Overdue"
 * is not a status: it is a new request past its follow-up time.
 */
enum ConsultationStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Scheduled = 'scheduled';
    case Closed = 'closed';
    case Spam = 'spam';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::Scheduled, self::Closed, self::Spam],
            // Back to New undoes a mistaken "Mark as contacted".
            self::Contacted => [self::New, self::Scheduled, self::Closed, self::Spam],
            self::Scheduled => [self::Contacted, self::Closed],
            // Re-open a closed request.
            self::Closed => [self::Contacted, self::Scheduled],
            // "Not spam".
            self::Spam => [self::New],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Scheduled => 'Scheduled',
            self::Closed => 'Closed',
            self::Spam => 'Spam',
        };
    }

    /** The timestamp recorded the first time a request reaches this status. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Contacted => 'contacted_at',
            self::Scheduled => 'scheduled_at',
            self::Closed => 'closed_at',
            default => null,
        };
    }
}
