<?php

declare(strict_types=1);

namespace Uptms\Users;

use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Uptms\Users\Models\User;
use Uptms\Users\Models\UserInvitation;
use Uptms\Users\Policies\UserPolicy;

final class UsersServiceProvider extends ModuleServiceProvider
{
    protected array $morphMap = [
        'user' => User::class,
        'user_invitation' => UserInvitation::class,
    ];

    protected array $policies = [
        User::class => UserPolicy::class,
    ];

    protected function bootModule(): void
    {
        // Invitation tokens are 64 random characters; this caps guessing.
        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
    }
}
