<?php

declare(strict_types=1);

namespace Uptms\Seo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Uptms\Seo\Services\SchemaGraph;

/**
 * The SEO, Social and Schema tabs send any subset of these. Scores are
 * never accepted from the client; the server computes them.
 */
class SaveSeoMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('seo.edit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:190'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'focus_keyphrase' => ['sometimes', 'nullable', 'string', 'max:100'],
            'keyphrases' => ['sometimes', 'nullable', 'array', 'max:8'],
            'keyphrases.*' => ['string', 'max:100', 'distinct'],
            'canonical_url' => ['sometimes', 'nullable', 'url:https', 'max:190'],
            'robots_index' => ['sometimes', 'boolean'],
            'robots_follow' => ['sometimes', 'boolean'],
            'in_sitemap' => ['sometimes', 'boolean'],
            'breadcrumb_title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'max_image_preview' => ['sometimes', Rule::in(['large', 'standard', 'none'])],
            'max_snippet' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'og_custom' => ['sometimes', 'boolean'],
            'og_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'og_description' => ['sometimes', 'nullable', 'string', 'max:240'],
            'og_image_id' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'twitter_card' => ['sometimes', Rule::in(['summary', 'summary_large_image'])],
            'schema_type' => ['sometimes', 'nullable', Rule::in(SchemaGraph::PAGE_TYPES)],
            'schema_about' => ['sometimes', 'nullable', 'string', 'max:120'],
            'schema_disabled' => ['sometimes', 'nullable', 'array'],
            'schema_disabled.*' => ['string', 'max:40'],
            'schema_custom' => ['sometimes', 'nullable', 'array'],
            'reviewed_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'last_reviewed_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'citations' => ['sometimes', 'nullable', 'array', 'max:12'],
            'citations.*.label' => ['required', 'string', 'max:160'],
            'citations.*.url' => ['required', 'url:https', 'max:300'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $custom = $this->input('schema_custom');

            if (is_array($custom) && $custom !== [] && SchemaGraph::customNodes($custom) === []) {
                $validator->errors()->add('schema_custom', 'Custom JSON-LD needs an "@type" (an object, or a list of objects).');
            }

            if (is_array($custom) && strlen((string) json_encode($custom)) > 20000) {
                $validator->errors()->add('schema_custom', 'Custom JSON-LD is limited to 20 KB.');
            }
        });
    }
}
