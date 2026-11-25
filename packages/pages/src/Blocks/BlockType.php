<?php

declare(strict_types=1);

namespace Uptms\Pages\Blocks;

use Closure;

/**
 * A section of the website (hero, FAQ, location …). `sources` maps the
 * block's saved data to the data other modules supply at read time, e.g.
 * the FAQ block's `group` → published FAQs of that group.
 */
final readonly class BlockType
{
    /**
     * @param  list<Field>  $fields
     * @param  (Closure(array<string, mixed>): array<string, array{0: string, 1: array<string, mixed>}>)|null  $sources  data → [as => [source key, params]]
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $description,
        public array $fields,
        public ?Closure $sources = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public function sourcesFor(array $data): array
    {
        return $this->sources === null ? [] : ($this->sources)($data);
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        $data = [];

        foreach ($this->fields as $field) {
            $data[$field->name] = match ($field->type) {
                'list', 'repeater' => [],
                'link' => ['label' => '', 'href' => ''],
                'toggle' => (bool) $field->default,
                'select' => $field->default,
                'image' => null,
                default => '',
            };
        }

        return $data;
    }
}
