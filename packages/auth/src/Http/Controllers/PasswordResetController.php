<?php

declare(strict_types=1);

namespace Uptms\Auth\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Users\Models\User;

/**
 * Figma screen 02. The answer never says whether an account exists.
 */
class PasswordResetController
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function forgot(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = mb_strtolower($validated['email']);

        // Invited (no password yet) and deactivated accounts get nothing.
        $eligible = User::query()->where('email', $email)->where('is_active', true)->whereNotNull('password')->exists();

        if ($eligible) {
            Password::broker()->sendResetLink(['email' => $email]);
            $this->audit->record('auth.password_reset_requested', null, null, ['email' => $email]);
        }

        return ApiResponse::success(null, 'If that email belongs to an account, a reset link is on its way.');
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        $status = Password::broker()->reset(
            ['email' => mb_strtolower($validated['email']), 'token' => $validated['token'], 'password' => $validated['password']],
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->saveQuietly();
                    // Every other session ends: whoever knew the old password is out.
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                    $this->audit->record('auth.password_reset', $user, userId: $user->id);
                });

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['This reset link is invalid or has expired. Request a new one.'],
            ]);
        }

        return ApiResponse::success(null, 'Password changed. Sign in with your new password.');
    }
}
