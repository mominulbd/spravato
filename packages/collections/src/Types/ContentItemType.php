<?php

declare(strict_types=1);

namespace Uptms\Collections\Types;

use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Models\ContentItem;

/** Trust points and journey steps share `content_items`, split by group. */
final class ContentItemType extends CollectionType
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $group,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function model(): string
    {
        return ContentItem::class;
    }

    public function fixedGroup(): string
    {
        return $this->group;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:600'],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'link_label' => ['nullable', 'string', 'max:60'],
            'link_url' => ['nullable', 'required_with:link_label', 'string', 'max:190', 'regex:/^(\/|#|https:\/\/|tel:|mailto:)/'],
        ];
    }

    public function present(CollectionItem $item, array $images): array
    {
        /** @var ContentItem $item */
        return [
            'title' => $item->title,
            'body' => $item->body,
            'icon' => $item->icon,
            'link_label' => $item->link_label,
            'link_url' => $item->link_url,
        ];
    }

    public function itemLabel(CollectionItem $item): string
    {
        /** @var ContentItem $item */
        return $this->label.' · '.mb_substr($item->title, 0, 80);
    }

    public function searchColumns(): array
    {
        return ['title', 'body'];
    }
}
