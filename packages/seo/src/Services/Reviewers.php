<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Database\Eloquent\Model;
use Uptms\Permissions\Contracts\HasPermissions;

/**
 * Staff who may sign off health content (content.review), for the Medical
 * review card and `reviewedBy` in the JSON-LD. Read through the auth user
 * model so this module doesn't depend on the users package.
 */
final class Reviewers
{
    /** @return list<array{id: int, name: string}> */
    public function all(): array
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model');

        return array_values($model::query()->where('is_active', true)->whereNotNull('password')->orderBy('name')->get()
            ->filter(fn (Model $user): bool => $user instanceof HasPermissions && $user->hasPermission('content.review'))
            ->map(fn (Model $user): array => ['id' => (int) $user->getKey(), 'name' => (string) $user->getAttribute('name')])
            ->all());
    }

    public function name(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model');
        $name = $model::query()->withoutGlobalScopes()->whereKey($id)->value('name');

        return is_string($name) ? $name : null;
    }
}
