<?php

declare(strict_types=1);

namespace Uptms\Users\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Users\Actions\InviteUserAction;
use Uptms\Users\Actions\RemoveUserAction;
use Uptms\Users\Actions\SendInvitationAction;
use Uptms\Users\Actions\UpdateUserAction;
use Uptms\Users\Domain\Enums\UserStatus;
use Uptms\Users\Http\Requests\InviteUserRequest;
use Uptms\Users\Http\Requests\UpdateUserRequest;
use Uptms\Users\Http\Resources\UserResource;
use Uptms\Users\Models\User;
use Uptms\Users\Services\StaffGuard;

class UserController
{
    private const array SORTS = ['name', 'email', 'last_seen_at', 'created_at'];

    /** Figma 25: search, status and role filters; tab counts in `meta.counts`. */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.status' => ['sometimes', Rule::enum(UserStatus::class)],
            'filter.role' => ['sometimes', 'string', 'max:60'],
            'sort' => ['sometimes', Rule::in([...self::SORTS, ...array_map(fn (string $s): string => '-'.$s, self::SORTS)])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filter = $validated['filter'] ?? [];
        $search = $validated['search'] ?? null;
        $sort = $validated['sort'] ?? 'name';

        $users = User::query()
            ->with(['role', 'invitations'])
            ->when($search !== null && $search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when(isset($filter['status']), fn (Builder $q) => $q->withStatus(UserStatus::from($filter['status'])))
            ->when(isset($filter['role']), fn (Builder $q) => $q->whereHas('role', fn (Builder $r) => $r->where('slug', $filter['role'])))
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        return ApiResponse::paginated($users, UserResource::class, 'Users retrieved.', [
            'counts' => [
                'all' => User::query()->count(),
                'invited' => User::query()->withStatus(UserStatus::Invited)->count(),
                'without_two_factor' => User::query()->withStatus(UserStatus::Active)->whereNull('two_factor_confirmed_at')->count(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return ApiResponse::success(new UserResource($user->load(['role', 'invitations'])), 'User retrieved.');
    }

    public function store(InviteUserRequest $request, InviteUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @phpstan-ignore argument.type (validated shape) */
        $user = $action->execute($request->validated(), $actor);

        return ApiResponse::created(new UserResource($user->load(['role', 'invitations'])), 'Invitation sent.');
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @phpstan-ignore argument.type (validated shape) */
        $user = $action->execute($user, $request->validated(), $actor);

        return ApiResponse::success(new UserResource($user->load(['role', 'invitations'])), 'User updated.');
    }

    public function destroy(Request $request, User $user, RemoveUserAction $action): JsonResponse
    {
        Gate::authorize('delete', $user);
        /** @var User $actor */
        $actor = $request->user();
        $action->execute($user, $actor);

        return ApiResponse::success(null, 'User removed.');
    }

    public function resendInvitation(Request $request, User $user, SendInvitationAction $action, StaffGuard $guard): JsonResponse
    {
        Gate::authorize('update', $user);
        /** @var User $actor */
        $actor = $request->user();
        $guard->assertMayManage($actor, $user);
        $action->execute($user, $actor);

        return ApiResponse::success(new UserResource($user->load(['role', 'invitations'])), 'Invitation sent again.');
    }

    /** For a lost phone: the person sets 2FA up again at their next sign-in. */
    public function resetTwoFactor(Request $request, User $user, StaffGuard $guard, AuditRecorder $audit): JsonResponse
    {
        Gate::authorize('update', $user);
        /** @var User $actor */
        $actor = $request->user();
        $guard->assertMayManage($actor, $user);

        DB::transaction(function () use ($user, $audit): void {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->saveQuietly();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $audit->record('user.two_factor_reset', $user);
        });

        return ApiResponse::success(new UserResource($user->load(['role', 'invitations'])), 'Two-factor authentication reset.');
    }
}
