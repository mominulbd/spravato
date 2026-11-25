<?php

declare(strict_types=1);

namespace Uptms\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Uptms\Media\Models\Media;

class ReplaceMediaRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:'.(int) config('media.max_document_kb')],
        ];
    }
}
