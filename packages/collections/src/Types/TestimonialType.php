<?php

declare(strict_types=1);

namespace Uptms\Collections\Types;

use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Models\Testimonial;

final class TestimonialType extends CollectionType
{
    public function key(): string
    {
        return 'testimonials';
    }

    public function label(): string
    {
        return 'Testimonials';
    }

    public function model(): string
    {
        return Testimonial::class;
    }

    public function rules(): array
    {
        return [
            'quote' => ['required', 'string', 'max:600'],
            'author_name' => ['required', 'string', 'max:120'],
            'source_label' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url:https', 'max:190'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    public function present(CollectionItem $item, array $images): array
    {
        /** @var Testimonial $item */
        return [
            'quote' => $item->quote,
            'author_name' => $item->author_name,
            'source_label' => $item->source_label,
            'source_url' => $item->source_url,
            'rating' => $item->rating,
        ];
    }

    public function itemLabel(CollectionItem $item): string
    {
        /** @var Testimonial $item */
        return 'Testimonial · '.$item->author_name;
    }

    public function searchColumns(): array
    {
        return ['quote', 'author_name'];
    }
}
