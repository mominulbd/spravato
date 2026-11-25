<?php

declare(strict_types=1);

namespace Uptms\AuditLog;

use App\Support\Packages\ModuleServiceProvider;
use Uptms\AuditLog\Services\AuditRecorder;

final class AuditLogServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        AuditRecorder::class => AuditRecorder::class,
    ];
}
