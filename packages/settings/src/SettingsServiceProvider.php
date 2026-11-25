<?php

declare(strict_types=1);

namespace Uptms\Settings;

use App\Support\Packages\ModuleServiceProvider;
use Uptms\Settings\Services\CacheClearer;
use Uptms\Settings\Services\OfficeHours;
use Uptms\Settings\Services\PublicSettings;
use Uptms\Settings\Services\Settings;
use Uptms\Settings\Services\SettingsRegistry;

final class SettingsServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        SettingsRegistry::class => SettingsRegistry::class,
        Settings::class => Settings::class,
        OfficeHours::class => OfficeHours::class,
        PublicSettings::class => PublicSettings::class,
        CacheClearer::class => CacheClearer::class,
    ];
}
