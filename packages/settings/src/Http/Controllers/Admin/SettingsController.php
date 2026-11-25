<?php

declare(strict_types=1);

namespace Uptms\Settings\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Uptms\Settings\Services\CacheClearer;
use Uptms\Settings\Services\OfficeHours;
use Uptms\Settings\Services\PublicSettings;
use Uptms\Settings\Services\Settings;
use Uptms\Settings\Services\SettingsRegistry;

/** Figma 24. Route-gated by settings.manage; every change is audited by the services. */
class SettingsController
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SettingsRegistry $registry,
        private readonly OfficeHours $hours,
        private readonly CacheClearer $cache,
    ) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->payload(), 'Settings retrieved.');
    }

    public function update(Request $request, string $group): JsonResponse
    {
        abort_unless(in_array($group, $this->registry->groups(), true), 404);

        $validated = $request->validate($this->registry->rules($group));
        $values = array_intersect_key($validated, $this->registry->definitions()[$group]);

        // Normalise optional strings so the website can rely on '' vs null.
        foreach ($values as $key => $value) {
            if ($value === null && is_string($this->registry->definitions()[$group][$key]['default'])) {
                $values[$key] = '';
            }
        }

        $this->settings->update($group, $values);

        return ApiResponse::success($this->payload(), 'Settings saved.');
    }

    public function updateHours(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'array', 'size:7'],
            'days.*.day' => ['required', 'integer', 'between:1,7', 'distinct'],
            'days.*.is_open' => ['required', 'boolean'],
            'days.*.opens_at' => ['nullable', 'required_if_accepted:days.*.is_open', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'required_if_accepted:days.*.is_open', 'date_format:H:i'],
            'closures' => ['present', 'array', 'max:60'],
            'closures.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'closures.*.label' => ['required', 'string', 'max:80'],
        ]);

        foreach ($validated['days'] as $index => $day) {
            if ($day['is_open'] && $day['opens_at'] >= $day['closes_at']) {
                return ApiResponse::error(ErrorCode::ValidationFailed, 'Closing time must be after opening time.', [
                    "days.{$index}.closes_at" => ['Closing time must be after opening time.'],
                ]);
            }
        }

        /** @var list<array{day: int, is_open: bool, opens_at?: string|null, closes_at?: string|null}> $days */
        $days = $validated['days'];
        /** @var list<array{date: string, label: string}> $closures */
        $closures = $validated['closures'];
        $this->hours->update($days, $closures);

        return ApiResponse::success($this->payload(), 'Office hours saved.');
    }

    public function clearCache(Request $request): JsonResponse
    {
        $validated = $request->validate(['scope' => ['required', 'string', 'in:'.implode(',', CacheClearer::SCOPES)]]);

        $this->cache->clear($validated['scope']);

        return ApiResponse::success([
            'scope' => $validated['scope'],
            'cleared_at' => now()->toIso8601String(),
            'website_connected' => $this->cache->websiteConnected(),
        ], 'Cache cleared.');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $groups = [];

        foreach ($this->registry->groups() as $group) {
            $groups[$group] = $this->settings->group($group);
        }

        return [
            ...$groups,
            'hours' => [
                'days' => $this->hours->week(),
                'closures' => $this->hours->closures(),
                'short' => $this->hours->short(),
            ],
            'phone_href' => PublicSettings::telHref((string) $groups['profile']['phone']),
        ];
    }
}
