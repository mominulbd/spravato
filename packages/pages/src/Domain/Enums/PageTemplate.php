<?php

declare(strict_types=1);

namespace Uptms\Pages\Domain\Enums;

/** Starting layouts for new pages (Figma 06 "Template" column). */
enum PageTemplate: string
{
    case Landing = 'landing';
    case Standard = 'standard';
    case Contact = 'contact';
    case Hub = 'hub';
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Landing => 'Landing',
            self::Standard => 'Standard',
            self::Contact => 'Contact',
            self::Hub => 'Hub',
            self::Legal => 'Legal',
        };
    }

    /** @return list<string> block types a new page starts with */
    public function starterBlocks(): array
    {
        return match ($this) {
            self::Landing => ['hero', 'copy_panel', 'cta_band'],
            self::Standard => ['hero', 'rich_text', 'cta_band'],
            self::Contact => ['consultation', 'location', 'cta_band'],
            self::Hub => ['hero', 'link_cards', 'cta_band'],
            self::Legal => ['rich_text'],
        };
    }
}
