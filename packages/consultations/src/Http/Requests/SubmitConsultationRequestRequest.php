<?php

declare(strict_types=1);

namespace Uptms\Consultations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Uptms\Consultations\Domain\Enums\BestTime;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Domain\Enums\ContactMethod;
use Uptms\Consultations\Domain\Enums\PriorTreatment;

/**
 * The website's "Find out if you qualify for TMS" form (Contact page and
 * the Home hero's popup), posted by the Next.js server. Laravel is the
 * one validator; the site mirrors these rules for instant feedback. Error
 * messages speak to the patient.
 */
class SubmitConsultationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 ()+.\-]+$/', function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = preg_replace('/\D/', '', (string) $value) ?? '';

                if (strlen($digits) < 10 || strlen($digits) > 11 || (strlen($digits) === 11 && $digits[0] !== '1')) {
                    $fail('Enter a 10-digit U.S. phone number, e.g. (253) 555-0142.');
                }
            }],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'insurance' => ['required', 'string', 'max:120'],
            'prior_treatment' => ['required', Rule::enum(PriorTreatment::class)],
            'contact_method' => ['required', Rule::enum(ContactMethod::class)],
            // Optional: a self-check result links here with its topic.
            'topic' => ['nullable', Rule::enum(ConsultationTopic::class)],
            'best_time' => ['nullable', Rule::enum(BestTime::class)],
            'referred_by' => ['nullable', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:1000'],
            'source_page' => ['nullable', 'string', 'max:190', 'regex:/^\/(?!\/)/'],
            'referrer' => ['nullable', 'string', 'max:2000'],
            'utm' => ['nullable', 'array:source,medium,campaign'],
            'utm.*' => ['nullable', 'string', 'max:100'],
            'turnstile_token' => ['nullable', 'string', 'max:4096'],
            // Honeypot: people never see this field.
            'website' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Enter your first name so the team knows who to ask for.',
            'first_name.max' => 'Keep your first name under 60 characters.',
            'last_name.required' => 'Enter your last name.',
            'last_name.max' => 'Keep your last name under 60 characters.',
            'phone.required' => 'Enter a phone number the clinic can call.',
            'phone.regex' => 'Enter a 10-digit U.S. phone number, e.g. (253) 555-0142.',
            'phone.max' => 'Enter a 10-digit U.S. phone number, e.g. (253) 555-0142.',
            'email.required' => 'Enter an email address the clinic can reply to.',
            'email.email' => 'Enter a valid email address, e.g. you@example.com.',
            'email.max' => 'Enter a valid email address, e.g. you@example.com.',
            'insurance.required' => 'Choose your insurance, or “Other” or “No insurance”.',
            'insurance.max' => 'Keep the insurance name under 120 characters.',
            'prior_treatment.required' => 'Tell us whether you have tried therapy or antidepressants.',
            'prior_treatment.enum' => 'Choose an answer from the list.',
            'contact_method.required' => 'Choose how you would like the clinic to reach you.',
            'contact_method.enum' => 'Choose an answer from the list.',
            'topic.enum' => 'Choose what you would like to talk about.',
            'best_time.enum' => 'Choose a time from the list.',
            'referred_by.max' => 'Keep the referring provider under 160 characters.',
            'message.max' => 'Keep your question under 1,000 characters.',
        ];
    }

    public function isBot(): bool
    {
        return trim((string) $this->input('website', '')) !== '';
    }
}
