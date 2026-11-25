<?php

declare(strict_types=1);

namespace Uptms\Settings\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Publishing\Events\ContentPublished;

/**
 * Clinic settings → Cache. `website` drops the public API cache and asks the
 * website to revalidate everything (the `content` tag covers every page).
 * `application` also empties the whole Laravel cache store (settings, SEO
 * settings, sign-in throttles) and compiled views. Sessions and queued jobs
 * live in the database, so nobody is signed out and no work is lost.
 */
class CacheClearer
{
    public const array SCOPES = ['website', 'application'];

    public function __construct(private readonly AuditRecorder $audit) {}

    public function clear(string $scope): void
    {
        if ($scope === 'application') {
            Cache::flush();
            Artisan::call('view:clear');
        }

        ContentPublished::dispatch(['content']);

        $this->audit->record('settings.cache_cleared', null, null, ['scope' => $scope], label: $scope);
    }

    /** False when FRONTEND_URL or FRONTEND_REVALIDATE_SECRET is missing: the site then refreshes within its cache life. */
    public function websiteConnected(): bool
    {
        $url = config('services.frontend.url');
        $secret = config('services.frontend.revalidate_secret');

        return is_string($url) && $url !== '' && is_string($secret) && $secret !== '';
    }
}
