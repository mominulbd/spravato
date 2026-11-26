<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/**
 * "What would you like to discuss?" in the Spravato consultation dialog.
 * Every consultation button on the page opens the dialog with one of these
 * chosen (`#consultation?topic=insurance`). Stored as the value.
 */
enum ConsultationTopic: string
{
    case Spravato = 'spravato';
    case Eligibility = 'eligibility';
    case FirstVisit = 'first_visit';
    case Insurance = 'insurance';
    case Question = 'question';

    /** In the dialog. */
    public function label(): string
    {
        return match ($this) {
            self::Spravato => 'Spravato consultation',
            self::Eligibility => 'Whether Spravato is right for me',
            self::FirstVisit => 'What a treatment visit involves',
            self::Insurance => 'Insurance & cost',
            self::Question => 'A question for the team',
        };
    }

    /** In the admin's tables and charts. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Eligibility => 'Eligibility',
            self::FirstVisit => 'Treatment visits',
            self::Question => 'General question',
            default => $this->label(),
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $topic): array => ['value' => $topic->value, 'label' => $topic->label()], self::cases());
    }
}
