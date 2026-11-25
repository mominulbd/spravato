<?php

declare(strict_types=1);

namespace Uptms\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Uptms\Pages\Domain\Enums\PageTemplate;
use Uptms\Pages\Models\Page;

class PageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page instanceof Page
            ? $this->user()?->can('updateSettings', $page) ?? false
            : $this->user()?->can('create', Page::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'template' => ['required', Rule::enum(PageTemplate::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return ['slug.regex' => 'Use lowercase letters, numbers and single dashes.'];
    }
}
