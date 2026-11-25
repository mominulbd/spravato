<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Seo\Models\SeoSetting;

/**
 * Site-wide SEO defaults (Figma 23): title templates, the default share
 * image, social handle, verification codes, the Google Tag Manager
 * container, sitemap and robots.txt options. Grouped by the screen's tabs;
 * missing keys read as defaults.
 */
class SeoSettings
{
    private const string CACHE_KEY = 'seo:settings';

    public const array SEPARATORS = ['|', '·', '—', '–'];

    public const string DEFAULT_ROBOTS = "User-agent: *\nDisallow: /admin\nDisallow: /api/\nDisallow: /preview\nDisallow: /*?preview=\n";

    /**
     * Pages a visitor reaches only to describe a health problem. HHS online-tracking
     * guidance treats a tag firing there as a disclosure, so the website is told to
     * leave the container off them while `gtm_exclude_sensitive` is on.
     *
     * @var list<string>
     */
    public const array SENSITIVE_PATHS = ['/contact'];

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @return array<string, array<string, array{default: mixed, rules: list<mixed>}>>
     */
    public function definitions(): array
    {
        $template = ['required', 'string', 'max:120', 'regex:/%title%/'];

        return [
            'general' => [
                'site_name' => ['default' => 'UPTMS', 'rules' => ['required', 'string', 'max:60']],
                'separator' => ['default' => '|', 'rules' => ['required', 'in:'.implode(',', self::SEPARATORS)]],
                'template_page' => ['default' => '%title% %sep% %site%', 'rules' => $template],
                'default_og_image_id' => ['default' => null, 'rules' => ['nullable', 'integer']],
            ],
            'organization' => [
                'schema_type' => ['default' => 'MedicalClinic', 'rules' => ['required', 'in:MedicalClinic,MedicalBusiness,Physician']],
                'medical_specialty' => ['default' => 'Psychiatric', 'rules' => ['nullable', 'string', 'max:60']],
                'logo_id' => ['default' => null, 'rules' => ['nullable', 'integer']],
                'price_range' => ['default' => null, 'rules' => ['nullable', 'string', 'max:20']],
            ],
            'social' => [
                'x_handle' => ['default' => '', 'rules' => ['nullable', 'string', 'max:30', 'regex:/^@?[A-Za-z0-9_]{1,15}$/']],
            ],
            'verification' => [
                'google' => ['default' => '', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/']],
                'bing' => ['default' => '', 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/']],
            ],
            'analytics' => [
                // Off until an admin pastes a container id: the site ships with no third-party script.
                'gtm_enabled' => ['default' => false, 'rules' => ['boolean']],
                'gtm_container_id' => ['default' => '', 'rules' => ['nullable', 'required_if_accepted:gtm_enabled', 'string', 'regex:/^GTM-[A-Z0-9]{5,12}$/i']],
                'gtm_exclude_sensitive' => ['default' => true, 'rules' => ['boolean']],
            ],
            'sitemap' => [
                'include_pages' => ['default' => true, 'rules' => ['boolean']],
                'include_images' => ['default' => true, 'rules' => ['boolean']],
                'include_lastmod' => ['default' => true, 'rules' => ['boolean']],
                'indexnow' => ['default' => true, 'rules' => ['boolean']],
                'search_console_submit' => ['default' => true, 'rules' => ['boolean']],
                'robots_txt' => ['default' => self::DEFAULT_ROBOTS, 'rules' => ['required', 'string', 'max:5000']],
            ],
        ];
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The Google Tag Manager container the website should load, or null while
     * analytics are off or no container is set. `exclude_paths` are the paths
     * the site must render without the container (SENSITIVE_PATHS).
     *
     * @return array{id: string, exclude_paths: list<string>}|null
     */
    public function tagManager(): ?array
    {
        $id = $this->get('gtm_container_id');
        $id = is_string($id) ? trim($id) : '';

        if ($this->get('gtm_enabled') !== true || $id === '') {
            return null;
        }

        return [
            'id' => $id,
            'exclude_paths' => $this->get('gtm_exclude_sensitive') === false ? [] : self::SENSITIVE_PATHS,
        ];
    }

    /** @return array<string, mixed> */
    public function group(string $group): array
    {
        $all = $this->all();

        return array_intersect_key($all, $this->definitions()[$group] ?? []);
    }

    /** @return array<string, mixed> every key with its value or default */
    public function all(): array
    {
        $defaults = [];

        foreach ($this->definitions() as $group) {
            foreach ($group as $key => $definition) {
                $defaults[$key] = $definition['default'];
            }
        }

        // Internal keys (the IndexNow key, last notification) live in the same table but aren't settings.
        return array_merge($defaults, array_intersect_key($this->stored(), $defaults));
    }

    /** @return array<string, list<mixed>> */
    public function rules(string $group): array
    {
        return array_map(fn (array $definition): array => $definition['rules'], $this->definitions()[$group] ?? []);
    }

    /**
     * @param  array<string, mixed>  $values  validated keys of one group
     */
    public function update(string $group, array $values): void
    {
        $definitions = $this->definitions()[$group];
        $before = $this->group($group);

        DB::transaction(function () use ($values, $definitions, $before, $group): void {
            $changed = [];

            foreach ($values as $key => $value) {
                if (! array_key_exists($key, $definitions)) {
                    continue;
                }

                SeoSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

                if (($before[$key] ?? null) !== $value) {
                    $changed[$key] = $value;
                }
            }

            if ($changed !== []) {
                $this->audit->record('seo.settings_updated', null, array_intersect_key($before, $changed), $changed, label: $group);
            }
        });

        Cache::forget(self::CACHE_KEY);
        ContentPublished::dispatch(['content', 'seo']);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        /** @var array<string, mixed> */
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => SeoSetting::query()->pluck('value', 'key')->all());
    }
}
