<?php

declare(strict_types=1);

namespace Uptms\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Uptms\Media\Models\Media;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('media');

        return $media instanceof Media && ($this->user()?->can('update', $media) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filename' => ['sometimes', 'required', 'string', 'max:190'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:125'],
            'is_decorative' => ['sometimes', 'boolean'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:190'],
            'focal_x' => ['sometimes', 'numeric', 'between:0,100'],
            'focal_y' => ['sometimes', 'numeric', 'between:0,100'],
            'folder_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media_folders', 'id')],
        ];
    }
}
