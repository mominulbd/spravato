<?php

declare(strict_types=1);

namespace Uptms\Pages\Blocks;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Uptms\Media\Rules\PlaceableMedia;
use Uptms\Publishing\Services\BlockSources;

/**
 * Validates and normalises block data against its type's fields. Unknown
 * keys are dropped, missing ones get defaults, so the website always
 * receives the full, expected shape.
 */
final readonly class BlockValidator
{
    public const string SAFE_HREF = '/^(\/(?!\/)|#|https:\/\/|tel:|mailto:)/';

    public function __construct(
        private BlockTypes $types,
        private BlockSources $sources,
    ) {}

    /**
     * Drafts may leave required fields empty (`$strict` false: autosave and
     * Save draft); publishing and scheduling check them (`$strict` true).
     * Types, lengths, links and images are always checked.
     *
     * @param  list<array<string, mixed>>  $blocks  raw blocks from the request
     * @return list<array{id: int|null, type: string, data: array<string, mixed>, is_visible: bool}>
     *
     * @throws ValidationException
     */
    public function validate(array $blocks, bool $strict = true): array
    {
        $errors = [];
        $clean = [];

        foreach ($blocks as $index => $block) {
            $typeKey = is_string($block['type'] ?? null) ? $block['type'] : '';

            if (! $this->types->has($typeKey)) {
                $errors["blocks.{$index}.type"] = ['Unknown section type.'];

                continue;
            }

            $type = $this->types->get($typeKey);
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $validator = Validator::make($data, $this->rules($type->fields, strict: $strict), [], $this->attributes($type->fields));

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["blocks.{$index}.data.{$field}"] = $messages;
                }

                continue;
            }

            $clean[] = [
                'id' => isset($block['id']) && is_numeric($block['id']) ? (int) $block['id'] : null,
                'type' => $typeKey,
                'data' => $this->normalise($type->fields, $data),
                'is_visible' => (bool) ($block['is_visible'] ?? true),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * @param  list<Field>  $fields
     * @return array<string, list<mixed>>
     */
    public function rules(array $fields, string $prefix = '', bool $strict = true): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = $prefix.$field->name;
            $presence = $field->required && $strict ? 'required' : 'nullable';

            switch ($field->type) {
                case 'text':
                case 'headline':
                case 'textarea':
                case 'markdown':
                    $rules[$key] = [$presence, 'string', 'max:'.($field->max ?? 255)];

                    if ($field->name === 'anchor') {
                        $rules[$key][] = 'regex:/^[a-z0-9-]*$/';
                    }

                    break;
                case 'link':
                    // A label needs somewhere to go: an address, or a document (media_id).
                    $rules[$key] = ['nullable', 'array', $this->needsTarget('href', 'label', 'The link needs an address or a document.')];
                    $rules[$key.'.label'] = ['nullable', 'string', 'max:80'];
                    $rules[$key.'.href'] = ['nullable', 'string', 'max:190', 'regex:'.self::SAFE_HREF];
                    $rules[$key.'.media_id'] = ['nullable', 'integer', new PlaceableMedia('document')];
                    break;
                case 'image':
                    $rules[$key] = ['nullable', 'integer', new PlaceableMedia];
                    break;
                case 'document':
                    $rules[$key] = ['nullable', 'integer', new PlaceableMedia('document')];
                    break;
                case 'list':
                    $rules[$key] = ['nullable', 'array', 'max:'.($field->maxItems ?? 10)];
                    $rules[$key.'.*'] = ['required', 'string', 'max:'.($field->max ?? 200)];
                    break;
                case 'repeater':
                    $rules[$key] = ['nullable', 'array', 'max:'.($field->maxItems ?? 10)];
                    $rules = array_merge($rules, $this->rules($field->fields, $key.'.*.', $strict));

                    // Link rows (label, href, document): the document stands in for the address.
                    if (($document = $this->documentField($field->fields)) !== null && $this->hasField($field->fields, 'href')) {
                        $rules[$key.'.*'] = ['array', $this->needsTarget('href', 'label', 'Add a link or choose a document.', $document)];
                        $rules[$key.'.*.href'] = [...($rules[$key.'.*.href'] ?? ['nullable']), 'regex:'.self::SAFE_HREF];
                    }

                    break;
                case 'select':
                    $rules[$key] = [$strict ? 'required' : 'nullable', 'in:'.implode(',', array_column($field->options, 'value'))];
                    break;
                case 'toggle':
                    $rules[$key] = ['nullable', 'boolean'];
                    break;
                case 'source':
                    $options = $field->source !== null ? array_column($this->sources->options($field->source), 'value') : [];
                    $rules[$key] = [$strict ? 'required' : 'nullable', 'string', 'max:80', ...($options !== [] ? ['in:'.implode(',', $options)] : [])];
                    break;
            }
        }

        return $rules;
    }

    /**
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalise(array $fields, array $data): array
    {
        $clean = [];

        foreach ($fields as $field) {
            $value = $data[$field->name] ?? null;

            $clean[$field->name] = match ($field->type) {
                'link' => $this->normaliseLink($value),
                'image', 'document' => is_numeric($value) ? (int) $value : null,
                'list' => array_values(array_map(fn (mixed $item): string => trim((string) $item), is_array($value) ? $value : [])),
                'repeater' => array_values(array_map(fn (mixed $row): array => $this->normalise($field->fields, is_array($row) ? $row : []), is_array($value) ? $value : [])),
                'toggle' => (bool) $value,
                'select' => is_string($value) ? $value : $field->default,
                default => is_string($value) ? trim($value) : '',
            };
        }

        return $clean;
    }

    /**
     * Every media id a block's data places, with the field label as context.
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $data
     * @return list<array{media_id: int, context: string}>
     */
    public function mediaIn(array $fields, array $data, string $context = ''): array
    {
        $found = [];

        foreach ($fields as $field) {
            $value = $data[$field->name] ?? null;

            if (in_array($field->type, ['image', 'document'], true) && is_int($value)) {
                $found[] = ['media_id' => $value, 'context' => trim($context.' '.$field->label)];
            } elseif ($field->type === 'link' && is_array($value) && is_int($value['media_id'] ?? null)) {
                $found[] = ['media_id' => $value['media_id'], 'context' => trim($context.' '.$field->label)];
            } elseif ($field->type === 'repeater' && is_array($value)) {
                foreach ($value as $row) {
                    if (is_array($row)) {
                        /** @var array<string, mixed> $row */
                        $found = array_merge($found, $this->mediaIn($field->fields, $row, $context));
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Document ids placed as links (link fields' media_id, document fields).
     * The presenter resolves these to the files' current addresses.
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public function documentsIn(array $fields, array $data): array
    {
        $found = [];

        foreach ($fields as $field) {
            $value = $data[$field->name] ?? null;

            if ($field->type === 'document' && is_int($value)) {
                $found[] = $value;
            } elseif ($field->type === 'link' && is_array($value) && is_int($value['media_id'] ?? null)) {
                $found[] = $value['media_id'];
            } elseif ($field->type === 'repeater' && is_array($value)) {
                foreach ($value as $row) {
                    if (is_array($row)) {
                        /** @var array<string, mixed> $row */
                        $found = array_merge($found, $this->documentsIn($field->fields, $row));
                    }
                }
            }
        }

        return $found;
    }

    /**
     * The name of a row's document field, if it has one.
     *
     * @param  list<Field>  $fields
     */
    public function documentField(array $fields): ?string
    {
        foreach ($fields as $field) {
            if ($field->type === 'document') {
                return $field->name;
            }
        }

        return null;
    }

    /**
     * @param  list<Field>  $fields
     */
    private function hasField(array $fields, string $name): bool
    {
        return in_array($name, array_map(fn (Field $field): string => $field->name, $fields), true);
    }

    /**
     * {label, href} plus media_id only when a document is chosen, so plain
     * links keep their two-key shape.
     *
     * @return array{label: string, href: string, media_id?: int}
     */
    private function normaliseLink(mixed $value): array
    {
        $link = [
            'label' => is_array($value) && is_string($value['label'] ?? null) ? trim($value['label']) : '',
            'href' => is_array($value) && is_string($value['href'] ?? null) ? trim($value['href']) : '',
        ];

        if (is_array($value) && is_numeric($value['media_id'] ?? null)) {
            $link['media_id'] = (int) $value['media_id'];
        }

        return $link;
    }

    /**
     * A filled label needs an address or a document.
     */
    private function needsTarget(string $href, string $label, string $message, string $document = 'media_id'): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($href, $label, $message, $document): void {
            if (! is_array($value)) {
                return;
            }

            $filled = fn (mixed $item): bool => is_int($item) || (is_string($item) && trim($item) !== '');

            if ($filled($value[$label] ?? null) && ! $filled($value[$href] ?? null) && ! $filled($value[$document] ?? null)) {
                $fail($message);
            }
        };
    }

    /**
     * @param  list<Field>  $fields
     * @return array<string, string>
     */
    private function attributes(array $fields): array
    {
        $names = [];

        foreach ($fields as $field) {
            $names[$field->name] = mb_strtolower($field->label);
            $names[$field->name.'.href'] = mb_strtolower($field->label).' link';
            $names[$field->name.'.label'] = mb_strtolower($field->label).' label';
        }

        return $names;
    }
}
