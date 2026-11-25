<?php

declare(strict_types=1);

namespace Uptms\Collections\Types;

use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Models\InsurancePartner;
use Uptms\Media\Rules\PlaceableMedia;

final class InsuranceType extends CollectionType
{
    public function key(): string
    {
        return 'insurance';
    }

    public function label(): string
    {
        return 'Insurance partners';
    }

    public function model(): string
    {
        return InsurancePartner::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'logo_id' => ['nullable', 'integer', new PlaceableMedia],
            'url' => ['nullable', 'url:https', 'max:190'],
            'note' => ['nullable', 'string', 'max:190'],
        ];
    }

    public function present(CollectionItem $item, array $images): array
    {
        /** @var InsurancePartner $item */
        return [
            'name' => $item->name,
            'logo' => $item->logo_id !== null ? $images[$item->logo_id] ?? null : null,
            'url' => $item->url,
            'note' => $item->note,
        ];
    }

    public function itemLabel(CollectionItem $item): string
    {
        /** @var InsurancePartner $item */
        return 'Insurance · '.$item->name;
    }

    public function searchColumns(): array
    {
        return ['name'];
    }

    public function mediaFields(): array
    {
        return ['logo_id' => 'Logo'];
    }
}
