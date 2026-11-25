<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Figma 19's Core Web Vitals card: what real Chrome users on phones
 * experienced over the last 28 days, from the Chrome UX Report API (needs
 * CRUX_API_KEY). Small sites may have no field data yet; then null.
 */
class CoreWebVitals
{
    private const string API = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord';

    /** Google's "good" and "poor" boundaries at the 75th percentile. */
    public const array METRICS = [
        'largest_contentful_paint' => ['key' => 'lcp', 'label' => 'Largest Contentful Paint', 'unit' => 'ms', 'good' => 2500, 'poor' => 4000],
        'interaction_to_next_paint' => ['key' => 'inp', 'label' => 'Interaction to Next Paint', 'unit' => 'ms', 'good' => 200, 'poor' => 500],
        'cumulative_layout_shift' => ['key' => 'cls', 'label' => 'Cumulative Layout Shift', 'unit' => '', 'good' => 0.1, 'poor' => 0.25],
    ];

    public function configured(): bool
    {
        return trim((string) config('seo.crux_key')) !== '';
    }

    /**
     * @return array{passing: bool, metrics: list<array{key: string, label: string, value: float, unit: string, rating: string, good: float, poor: float}>}|null
     */
    public function fetch(string $origin): ?array
    {
        if (! $this->configured() || $origin === '') {
            return null;
        }

        try {
            $response = Http::timeout(15)->post(self::API.'?key='.rawurlencode((string) config('seo.crux_key')), [
                'origin' => $origin,
                'formFactor' => 'PHONE',
                'metrics' => array_keys(self::METRICS),
            ]);
        } catch (Throwable $e) {
            Log::warning('Chrome UX Report request failed.', ['exception' => $e::class]);

            return null;
        }

        // 404: Google has too little traffic data for this origin yet.
        if (! $response->successful()) {
            if ($response->status() !== 404) {
                Log::warning('Chrome UX Report refused a request.', ['status' => $response->status()]);
            }

            return null;
        }

        $metrics = [];

        foreach (self::METRICS as $name => $metric) {
            $p75 = $response->json("record.metrics.{$name}.percentiles.p75");

            if (! is_numeric($p75)) {
                continue;
            }

            $value = (float) $p75;
            $metrics[] = [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'value' => $value,
                'unit' => $metric['unit'],
                'rating' => $value <= $metric['good'] ? 'good' : ($value <= $metric['poor'] ? 'needs_improvement' : 'poor'),
                'good' => (float) $metric['good'],
                'poor' => (float) $metric['poor'],
            ];
        }

        if ($metrics === []) {
            return null;
        }

        // Google's assessment: passing when every metric with data is good.
        return ['passing' => collect($metrics)->every(fn (array $m): bool => $m['rating'] === 'good'), 'metrics' => $metrics];
    }
}
