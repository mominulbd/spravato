<?php

declare(strict_types=1);

namespace Uptms\Users\Services;

use Illuminate\Database\Eloquent\Builder;
use Uptms\Permissions\Services\PermissionRegistry;
use Uptms\Users\Domain\Enums\UserStatus;
use Uptms\Users\Models\User;

/**
 * How other modules look up staff without querying the users table:
 * names for display, and who may be given a piece of work (e.g. the
 * consultation inbox's "Assigned to" list).
 */
final class StaffDirectory
{
    /**
     * Active staff holding a permission, by name.
     *
     * @return list<array{id: int, name: string, initials: string}>
     */
    public function withPermission(string $key): array
    {
        return array_values(User::query()
            ->withStatus(UserStatus::Active)
            ->whereHas('role', fn (Builder $role) => $role
                ->where('slug', PermissionRegistry::SUPER_ADMIN)
                ->orWhereHas('permissions', fn (Builder $permission) => $permission->where('key', $key)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => $this->entry($user))
            ->all());
    }

    /** True when the account is active and holds the permission. */
    public function holds(int $userId, string $key): bool
    {
        $user = User::query()->withStatus(UserStatus::Active)->find($userId);

        return $user instanceof User && $user->hasPermission($key);
    }

    /**
     * Display names for ids, including removed staff (their history keeps a name).
     *
     * @param  list<int>  $ids
     * @return array<int, array{id: int, name: string, initials: string}>
     */
    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $entries = [];

        foreach (User::withTrashed()->whereIn('id', array_values(array_unique($ids)))->get(['id', 'name']) as $user) {
            $entries[$user->id] = $this->entry($user);
        }

        return $entries;
    }

    /** @return array{id: int, name: string, initials: string} */
    private function entry(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'initials' => self::initials($user->name)];
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';

        return mb_strtoupper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));
    }
}
