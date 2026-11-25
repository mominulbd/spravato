<?php

declare(strict_types=1);

namespace Uptms\Auth\Http\Controllers;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Auth\Http\Resources\CurrentUserResource;
use Uptms\Auth\Services\TwoFactorGate;
use Uptms\Auth\Services\TwoFactorService;
use Uptms\Users\Models\User;

/**
 * Email + password sign-in on the Sanctum session (docs/ARCHITECTURE.md §5),
 * Figma screen 01. While the Settings → Security switch is on, a user with
 * 2FA on gets a challenge instead of a session;
 * the SPA then posts the code to /auth/two-factor-challenge. The SPA calls
 * GET /sanctum/csrf-cookie first.
 */
class SessionController
{
    /** Session key holding the half-signed-in user between the two steps. */
    public const string PENDING = 'auth.two_factor.pending';

    private const int PENDING_SECONDS = 300;

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly TwoFactorGate $gate,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $this->assertSession($request);

        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', mb_strtolower($credentials['email']))->first();

        if ($user === null || $user->password === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->record('auth.login_failed', $user, null, ['email' => mb_strtolower($credentials['email'])], userId: $user?->id);

            throw new GenericDomainException(ErrorCode::InvalidCredentials, 'That email and password do not match an account.');
        }

        if (! $user->is_active) {
            $this->audit->record('auth.login_blocked', $user, userId: $user->id);

            throw new GenericDomainException(ErrorCode::AccountInactive, 'This account has been deactivated.');
        }

        if ($this->gate->challenges($user)) {
            $request->session()->put(self::PENDING, ['user_id' => $user->id, 'expires_at' => now()->addSeconds(self::PENDING_SECONDS)->getTimestamp()]);

            return ApiResponse::success(['two_factor' => true], 'Enter the code from your authenticator app.');
        }

        return $this->signIn($request, $user, 'password');
    }

    public function challenge(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'max:12'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:32'],
        ]);

        /** @var array{user_id: int, expires_at: int}|null $pending */
        $pending = $request->session()->get(self::PENDING);
        $user = $pending !== null && $pending['expires_at'] > now()->getTimestamp()
            ? User::query()->find($pending['user_id'])
            : null;

        if ($user === null || ! $user->is_active) {
            $request->session()->forget(self::PENDING);

            throw new GenericDomainException(ErrorCode::Unauthenticated, 'Your sign-in timed out. Enter your email and password again.');
        }

        $code = $validated['code'] ?? null;
        $recovery = $validated['recovery_code'] ?? null;
        $passed = is_string($code) && $code !== ''
            ? $twoFactor->checkCode($user, $code)
            : is_string($recovery) && $twoFactor->useRecoveryCode($user, $recovery);

        if (! $passed) {
            $this->audit->record('auth.two_factor_failed', $user, userId: $user->id);

            throw new GenericDomainException(ErrorCode::InvalidTwoFactorCode, 'That code did not work. Try the newest code from your app.', [
                is_string($code) && $code !== '' ? 'code' : 'recovery_code' => ['That code did not work.'],
            ]);
        }

        $request->session()->forget(self::PENDING);

        return $this->signIn($request, $user, is_string($code) && $code !== '' ? 'totp' : 'recovery_code');
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = Auth::guard('web')->id();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (is_int($userId)) {
            $this->audit->record('auth.logout', null, userId: $userId);
        }

        return ApiResponse::success(null, 'Signed out.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(new CurrentUserResource($user), 'Current user.');
    }

    /**
     * Sign-in needs Sanctum's stateful session. A request without one comes
     * from a host missing in SANCTUM_STATEFUL_DOMAINS: a deployment error.
     */
    private function assertSession(Request $request): void
    {
        if (! $request->hasSession()) {
            throw new LogicException('Sign-in reached the API without a session. Add '.$request->getHttpHost().' to SANCTUM_STATEFUL_DOMAINS.');
        }
    }

    private function signIn(Request $request, User $user, string $method): JsonResponse
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->saveQuietly();
        $this->audit->record('auth.login', $user, null, ['method' => $method], userId: $user->id);

        return ApiResponse::success(['two_factor' => false, 'user' => (new CurrentUserResource($user))->resolve($request)], 'Signed in.');
    }
}
