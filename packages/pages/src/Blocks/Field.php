<?php

declare(strict_types=1);

namespace Uptms\Pages\Blocks;

/**
 * One editable field of a block. The admin renders its form from these
 * descriptions (GET /admin/pages/block-types) and the API derives its
 * validation from the same list, so the two cannot drift apart.
 *
 * Types: text, headline (*asterisks* mark the accent words), textarea,
 * markdown (paragraphs, **bold**, *italic*, [links](/path)), link
 * {label, href, media_id?} (media_id: a PDF from the library, whose
 * current address replaces href when served), image (media id), document
 * (PDF media id; in a repeater row it supplies the row's href), list
 * (strings), repeater (rows of fields), select, toggle, source (a choice
 * offered by a block source).
 */
final readonly class Field
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public bool $required = false,
        public ?int $max = null,
        public ?string $help = null,
        public array $options = [],
        public array $fields = [],
        public ?int $maxItems = null,
        public ?string $source = null,
        public mixed $default = null,
        public ?string $group = null,
    ) {}

    public static function text(string $name, string $label, int $max = 120, bool $required = false, ?string $help = null): self
    {
        return new self($name, 'text', $label, $required, $max, $help);
    }

    public static function headline(string $name, string $label = 'Headline', int $max = 120, bool $required = true): self
    {
        return new self($name, 'headline', $label, $required, $max, 'Wrap words in *asterisks* to set them in blue, like the website.');
    }

    public static function textarea(string $name, string $label, int $max = 320, bool $required = false, ?string $help = null): self
    {
        return new self($name, 'textarea', $label, $required, $max, $help);
    }

    public static function markdown(string $name, string $label, int $max = 4000, bool $required = false): self
    {
        return new self($name, 'markdown', $label, $required, $max, 'Leave a blank line between paragraphs. **bold**, *italic*, [link text](/path), "- " bullets and "## Heading" lines work.');
    }

    public static function link(string $name, string $label): self
    {
        return new self($name, 'link', $label);
    }

    public static function image(string $name, string $label = 'Image'): self
    {
        return new self($name, 'image', $label, help: 'Only images with alt text can be placed.');
    }

    /** A PDF from the media library. In a repeater row beside `href`, it fills that href when served. */
    public static function document(string $name, string $label = 'Document', ?string $help = null): self
    {
        return new self($name, 'document', $label, help: $help ?? 'An official PDF from the media library. The link follows the file when it is replaced.');
    }

    public static function list(string $name, string $label, int $maxItems = 8, int $max = 200): self
    {
        return new self($name, 'list', $label, max: $max, maxItems: $maxItems);
    }

    /**
     * @param  list<Field>  $fields
     */
    public static function repeater(string $name, string $label, array $fields, int $maxItems = 10): self
    {
        return new self($name, 'repeater', $label, fields: $fields, maxItems: $maxItems);
    }

    /**
     * @param  array<string, string>  $options  value → label
     */
    public static function select(string $name, string $label, array $options, ?string $default = null): self
    {
        $list = [];

        foreach ($options as $value => $optionLabel) {
            $list[] = ['value' => (string) $value, 'label' => $optionLabel];
        }

        return new self($name, 'select', $label, true, options: $list, default: $default ?? (string) array_key_first($options));
    }

    public static function toggle(string $name, string $label, bool $default = false, ?string $help = null): self
    {
        return new self($name, 'toggle', $label, help: $help, default: $default);
    }

    public static function source(string $name, string $label, string $source, ?string $help = null): self
    {
        return new self($name, 'source', $label, true, help: $help, source: $source);
    }

    public static function rail(): self
    {
        return new self('rail', 'text', 'Section label', max: 80, help: 'Shown beside the section number (numbers follow the order of sections).');
    }

    public static function anchor(): self
    {
        return new self('anchor', 'text', 'Anchor', max: 40, help: 'Lets links jump here, e.g. #insurance. Lowercase letters and dashes.');
    }

    /** Put the field in a titled box in the form ("Primary button"). */
    public function in(string $group): self
    {
        return new self($this->name, $this->type, $this->label, $this->required, $this->max, $this->help, $this->options, $this->fields, $this->maxItems, $this->source, $this->default, $group);
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @return array<string, mixed>
     */
    public function toArray(array $options = []): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'required' => $this->required,
            'max' => $this->max,
            'help' => $this->help,
            'options' => $this->type === 'source' ? $options : $this->options,
            'fields' => array_map(fn (Field $field): array => $field->toArray(), $this->fields),
            'max_items' => $this->maxItems,
            'source' => $this->source,
            'default' => $this->default,
            'group' => $this->group,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
