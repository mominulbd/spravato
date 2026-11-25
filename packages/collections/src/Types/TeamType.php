<?php

declare(strict_types=1);

namespace Uptms\Collections\Types;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Models\TeamMember;
use Uptms\Media\Rules\PlaceableMedia;

final class TeamType extends CollectionType
{
    public const array PAGES = ['home' => 'Home', 'tms' => 'TMS', 'contact' => 'Contact'];

    public function key(): string
    {
        return 'team';
    }

    public function label(): string
    {
        return 'Team';
    }

    public function model(): string
    {
        return TeamMember::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'credentials' => ['nullable', 'string', 'max:60'],
            'role_title' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:600'],
            'highlights' => ['nullable', 'array', 'max:8'],
            'highlights.*' => ['required', 'string', 'max:60', 'distinct'],
            'photo_id' => ['nullable', 'integer', new PlaceableMedia],
            'show_on' => ['nullable', 'array'],
            'show_on.*' => ['required', Rule::in(array_keys(self::PAGES)), 'distinct'],
            'is_founder' => ['boolean'],
            'schema_type' => ['required', Rule::in(['Physician', 'Person'])],
            'licence_number' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function present(CollectionItem $item, array $images): array
    {
        /** @var TeamMember $item */
        return [
            'name' => $item->name,
            'credentials' => $item->credentials,
            'role_title' => $item->role_title,
            'initials' => collect(explode(' ', $item->name))->filter()->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode(''),
            'bio' => $item->bio,
            'highlights' => $item->highlights ?? [],
            'photo' => $item->photo_id !== null ? $images[$item->photo_id] ?? null : null,
            'show_on' => $item->show_on ?? [],
            'is_founder' => $item->is_founder,
            'schema_type' => $item->schema_type,
            'licence_number' => $item->licence_number,
        ];
    }

    public function itemLabel(CollectionItem $item): string
    {
        /** @var TeamMember $item */
        return 'Team · '.$item->name;
    }

    public function searchColumns(): array
    {
        return ['name', 'role_title'];
    }

    public function mediaFields(): array
    {
        return ['photo_id' => 'Headshot'];
    }

    public function applyPublicFilters(Builder $query, array $params): Builder
    {
        $page = $params['page'] ?? null;

        return $query
            ->when(is_string($page) && $page !== '', fn (Builder $q) => $q->whereJsonContains('show_on', $page))
            ->when(($params['founder'] ?? false) === true, fn (Builder $q) => $q->where('is_founder', true));
    }

    public function sourceOptions(): array
    {
        return array_map(fn (string $key, string $label): array => ['value' => $key, 'label' => "Shown on {$label}"], array_keys(self::PAGES), self::PAGES);
    }
}
