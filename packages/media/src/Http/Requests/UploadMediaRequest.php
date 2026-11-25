<?php

declare(strict_types=1);

namespace Uptms\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Uptms\Media\Models\Media;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Media::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The type is read from the file's bytes, not its name.
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:'.(int) config('media.max_document_kb')],
            'folder_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media_folders', 'id')],
        ];
    }
}
