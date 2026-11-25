<?php

declare(strict_types=1);

namespace Uptms\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Uptms\Users\Domain\Enums\UserStatus;
use Uptms\Users\Models\User;
use Uptms\Users\Models\UserInvitation;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->status();

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'credentials' => $this->credentials,
            'email' => $this->email,
            'role' => $this->role === null ? null : [
                'id' => $this->role->id,
                'slug' => $this->role->slug,
                'name' => $this->role->name,
            ],
            'status' => $status->value,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'requires_two_factor' => $this->requiresTwoFactor(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'invitation' => $this->when(
                $status === UserStatus::Invited && $this->relationLoaded('invitations'),
                fn (): ?array => $this->liveInvitation(),
            ),
        ];
    }

    /** @return array{sent_at: string|null, expires_at: string, expired: bool}|null */
    private function liveInvitation(): ?array
    {
        /** @var UserInvitation|null $latest */
        $latest = $this->invitations->sortByDesc('id')->first();

        return $latest === null ? null : [
            'sent_at' => $latest->created_at?->toIso8601String(),
            'expires_at' => $latest->expires_at->toIso8601String(),
            'expired' => ! $latest->isUsable(),
        ];
    }
}
