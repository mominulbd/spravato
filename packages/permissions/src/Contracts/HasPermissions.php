<?php

declare(strict_types=1);

namespace Uptms\Permissions\Contracts;

/**
 * What every module may ask of the acting user without depending on the
 * users package.
 */
interface HasPermissions
{
    public function hasPermission(string $key): bool;

    /** @return list<string> */
    public function permissionKeys(): array;

    public function isSuperAdmin(): bool;
}
