<?php

declare(strict_types=1);

namespace Uptms\Users\Http\Controllers;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Uptms\Users\Actions\AcceptInvitationAction;
use Uptms\Users\Http\Requests\AcceptInvitationRequest;

/**
 * The invitation link (Figma 01 variant). Anonymous: the token is the
 * credential. Accepting signs the person in so they can set up 2FA next.
 */
class InvitationController
{
    public function show(string $token, AcceptInvitationAction $action): JsonResponse
    {
        $invitation = $action->find($token);
        $user = $invitation->invitee();

        return ApiResponse::success([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ], 'Invitation is valid.');
    }

    public function accept(string $token, AcceptInvitationRequest $request, AcceptInvitationAction $action): JsonResponse
    {
        /** @var string $password */
        $password = $request->validated('password');
        $user = $action->execute($token, $password);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ApiResponse::success(['email' => $user->email], 'Password set. Welcome aboard.');
    }
}
