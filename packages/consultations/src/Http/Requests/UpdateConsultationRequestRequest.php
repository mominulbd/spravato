<?php

declare(strict_types=1);

namespace Uptms\Consultations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Models\ConsultationRequest;

/**
 * Status, assignment and follow-up time from Figma 05's Status card. The
 * route binding has already limited `{consultation}` to the caller's scope.
 */
class UpdateConsultationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $consultation = $this->route('consultation');
        $user = $this->user();

        if (! $consultation instanceof ConsultationRequest || $user === null) {
            return false;
        }

        if ($this->has('assigned_to') && ! $user->can('assign', ConsultationRequest::class)) {
            return false;
        }

        return $user->can('update', $consultation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(ConsultationStatus::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
            'follow_up_at' => ['sometimes', 'date', 'after:2020-01-01', 'before:+2 years'],
        ];
    }
}
