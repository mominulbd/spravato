<?php

declare(strict_types=1);

namespace Uptms\Seo\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Uptms\Media\Services\MediaPresenter;
use Uptms\Seo\Services\SchemaGraph;
use Uptms\Seo\Services\SeoSettings;
use Uptms\Seo\Services\TitleTemplate;

/** Figma 23: site-wide SEO defaults, saved one tab (group) at a time. */
class SeoSettingsController
{
    public function __construct(private readonly SeoSettings $settings) {}

    public function show(TitleTemplate $titles, SchemaGraph $schema, MediaPresenter $media): JsonResponse
    {
        $all = $this->settings->all();

        return ApiResponse::success([
            'values' => $all,
            'groups' => array_keys($this->settings->definitions()),
            'separators' => SeoSettings::SEPARATORS,
            'default_robots' => SeoSettings::DEFAULT_ROBOTS,
            'sensitive_paths' => SeoSettings::SENSITIVE_PATHS,
            'examples' => [
                'page' => $titles->for('page', 'TMS therapy'),
                'condition' => $titles->for('condition', 'OCD'),
                'post' => $titles->for('post', 'What a TMS session feels like', ['category' => 'Guides']),
            ],
            'clinic' => $schema->clinic(),
            'default_og_image' => $media->present(is_int($all['default_og_image_id']) ? $all['default_og_image_id'] : null),
            'site_url' => SchemaGraph::siteUrl(),
        ], 'SEO settings retrieved.');
    }

    public function update(Request $request, string $group): JsonResponse
    {
        abort_unless(in_array($group, array_keys($this->settings->definitions()), true), 404);
        $rules = $this->settings->rules($group);

        if (isset($rules['default_og_image_id'])) {
            $rules['default_og_image_id'] = ['nullable', 'integer', Rule::exists('media', 'id')];
        }
        if (isset($rules['logo_id'])) {
            $rules['logo_id'] = ['nullable', 'integer', Rule::exists('media', 'id')];
        }

        /** @var array<string, mixed> $values */
        $values = $request->validate(array_map(fn (array $r): array => ['sometimes', ...$r], $rules));

        if (isset($values['x_handle']) && is_string($values['x_handle']) && $values['x_handle'] !== '') {
            $values['x_handle'] = '@'.ltrim($values['x_handle'], '@');
        }

        // Container ids are upper-case; an empty field is stored as '' so the website can rely on it.
        if (array_key_exists('gtm_container_id', $values)) {
            $values['gtm_container_id'] = is_string($values['gtm_container_id']) ? strtoupper(trim($values['gtm_container_id'])) : '';
        }

        $this->settings->update($group, $values);

        return $this->show(app(TitleTemplate::class), app(SchemaGraph::class), app(MediaPresenter::class));
    }
}
