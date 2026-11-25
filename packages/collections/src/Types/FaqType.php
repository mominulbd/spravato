<?php

declare(strict_types=1);

namespace Uptms\Collections\Types;

use Illuminate\Database\Eloquent\Builder;
use Uptms\Collections\Models\CollectionItem;
use Uptms\Collections\Models\Faq;

/** FAQs are grouped by the page that shows them: home, tms, conditions/<slug>. */
final class FaqType extends CollectionType
{
    public const array BASE_GROUPS = ['home' => 'Home', 'tms' => 'TMS'];

    public function key(): string
    {
        return 'faqs';
    }

    public function label(): string
    {
        return 'FAQs';
    }

    public function model(): string
    {
        return Faq::class;
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+(\/[a-z0-9-]+)?$/'],
            'question' => ['required', 'string', 'max:200'],
            'answer' => ['required', 'string', 'max:800'],
            'in_schema' => ['boolean'],
        ];
    }

    public function present(CollectionItem $item, array $images): array
    {
        /** @var Faq $item */
        return [
            'group' => $item->group,
            'question' => $item->question,
            'answer' => $item->answer,
            'in_schema' => $item->in_schema,
        ];
    }

    public function itemLabel(CollectionItem $item): string
    {
        /** @var Faq $item */
        return 'FAQ · '.mb_substr($item->question, 0, 80);
    }

    public function searchColumns(): array
    {
        return ['question', 'answer'];
    }

    public function groupColumn(): string
    {
        return 'group';
    }

    public function applyPublicFilters(Builder $query, array $params): Builder
    {
        $group = $params['group'] ?? null;

        return is_string($group) && $group !== '' ? $query->where('group', $group) : $query;
    }

    public function sourceOptions(): array
    {
        $options = [];

        foreach (self::BASE_GROUPS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        $groups = Faq::query()->distinct()->orderBy('group')->pluck('group')->all();

        foreach ($groups as $group) {
            if (! isset(self::BASE_GROUPS[$group])) {
                $options[] = ['value' => (string) $group, 'label' => ucfirst(str_replace(['conditions/', '-'], ['Condition · ', ' '], (string) $group))];
            }
        }

        return $options;
    }
}
