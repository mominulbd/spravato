<?php

declare(strict_types=1);

namespace Uptms\Settings\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Settings\Models\Setting;

/**
 * Read and write clinic settings. Reads come from one cached array that is
 * rebuilt after every write; missing keys fall back to registry defaults.
 * This is the contract other modules use (never the settings table).
 */
class Settings
{
    private const string CACHE_KEY = 'settings:all';

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly AuditRecorder $audit,
    ) {}

    /** `profile.phone` */
    public function get(string $path): mixed
    {
        [$group, $key] = explode('.', $path, 2);

        return $this->group($group)[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $defaults = array_map(fn (array $definition): mixed => $definition['default'], $this->registry->definitions()[$group] ?? []);

        return array_merge($defaults, $this->stored()[$group] ?? []);
    }

    /**
     * @param  array<string, mixed>  $values  validated, keys of one group
     */
    public function update(string $group, array $values): void
    {
        $definitions = $this->registry->definitions()[$group];
        $before = $this->group($group);

        DB::transaction(function () use ($group, $values, $definitions, $before): void {
            $changed = [];

            foreach ($values as $key => $value) {
                if (! array_key_exists($key, $definitions)) {
                    continue;
                }

                Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);

                if ($before[$key] !== $value) {
                    $changed[$key] = $value;
                }
            }

            if ($changed !== []) {
                $this->audit->record('settings.updated', null, array_intersect_key($before, $changed), $changed, label: $group);
            }
        });

        $this->forget();
        ContentPublished::dispatch(['content', 'settings']);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array<string, mixed>> */
    private function stored(): array
    {
        /** @var array<string, array<string, mixed>> */
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $all = [];

            foreach (Setting::query()->get() as $setting) {
                $all[$setting->group][$setting->key] = $setting->value;
            }

            return $all;
        });
    }
}
