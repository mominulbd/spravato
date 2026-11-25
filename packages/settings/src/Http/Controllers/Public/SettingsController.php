<?php

declare(strict_types=1);

namespace Uptms\Settings\Http\Controllers\Public;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Uptms\Publishing\Services\PublicCache;
use Uptms\Settings\Services\PublicSettings;

class SettingsController
{
    public function show(PublicSettings $settings, PublicCache $cache): JsonResponse
    {
        return ApiResponse::success($cache->remember('settings', fn (): array => $settings->toArray()), 'Settings retrieved.');
    }
}
