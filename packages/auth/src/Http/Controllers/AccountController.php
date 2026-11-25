<?php

declare(strict_types=1);

namespace Uptms\Auth\Http\Controllers;

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ApiResponse;
use App\Support\Http\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Auth\Services\TwoFactorGate;
use Uptms\Auth\Services\TwoFactorService;
use Uptms\Users\Models\User;

/**
 * The signed-in user's own security: password and two-factor set-up. These
 * routes stay reachable while 2FA set-up is still required.
 */
class AccountController
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly TwoFactorGate $gate,
    ) {}

    public function twoFactorStatus(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return ApiResponse::success([
            'available' => $this->gate->isOn(),
            'enabled' => $user->hasTwoFactorEnabled(),
            'required' => $this->gate->requires($user),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
            'recovery_codes_left' => $user->hasTwoFactorEnabled() ? $this->twoFactor->remainingRecoveryCodes($user) : 0,
        ]);
    }

    public function beginTwoFactor(Request $request): JsonResponse
    {
        return ApiResponse::success($this->twoFactor->begin($this->user($request)), 'Scan the code with your authenticator app.');
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $codes = $this->twoFactor->confirm($this->user($request), $validated['code']);

        return ApiResponse::success(['recovery_codes' => $codes], 'Two-factor authentication is on.');
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);
        $user = $this->user($request);

        if ($this->gate->requires($user)) {
            throw new GenericDomainException(ErrorCode::Forbidden, 'Your role requires two-factor authentication, so it cannot be turned off.');
        }

        $this->twoFactor->disable($user);

        return ApiResponse::success(null, 'Two-factor authentication is off.');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);
        $user = $this->user($request);

        if (! $user->hasTwoFactorEnabled()) {
            throw new GenericDomainException(ErrorCode::Conflict, 'Turn on two-factor authentication first.');
        }

        return ApiResponse::success(['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($user)], 'New recovery codes created; the old ones no longer work.');
    }

    public function changePassword(Request $request, AuditRecorder $audit): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'string', 'max:255', 'confirmed', 'different:current_password', Password::min(12)->letters()->numbers()],
        ]);
        $user = $this->user($request);

        DB::transaction(function () use ($user, $validated, $request, $audit): void {
            $user->forceFill(['password' => $validated['password']])->saveQuietly();
            // Sign out every other device.
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            $audit->record('auth.password_changed', $user, userId: $user->id);
        });

        return ApiResponse::success(null, 'Password changed.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
