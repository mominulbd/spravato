<?php

declare(strict_types=1);

namespace Uptms\Consultations\Domain\Enums;

/** "How can we help?" on the Contact form. Stored as the value, shown as the label. */
enum ConsultationTopic: string
{
    case TmsDepression = 'tms_depression';
    case Insurance = 'insurance';
    case OcdSpecialty = 'ocd_specialty';
    case LovedOne = 'loved_one';
    case ProviderReferral = 'provider_referral';

    /** On the website form. */
    public function label(): string
    {
        return match ($this) {
            self::TmsDepression => 'TMS for depression',
            self::Insurance => 'Insurance & coverage',
            self::OcdSpecialty => 'Deep TMS for OCD / specialty care',
            self::LovedOne => 'Care for a loved one',
            self::ProviderReferral => 'Provider referral',
        };
    }

    /** In the admin's tables and charts. */
    public function shortLabel(): string
    {
        return $this === self::OcdSpecialty ? 'Deep TMS for OCD' : $this->label();
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $topic): array => ['value' => $topic->value, 'label' => $topic->label()], self::cases());
    }
}
