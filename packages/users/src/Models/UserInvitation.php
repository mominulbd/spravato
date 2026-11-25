<?php

declare(strict_types=1);

namespace Uptms\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One emailed invitation link. Only the sha256 of the token is stored; the
 * newest unaccepted, unexpired row for a user is the live one.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $invited_by
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property-read User|null $user  null once the account is removed
 */
class UserInvitation extends Model
{
    protected $fillable = ['user_id', 'invited_by', 'token_hash', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The invited account; only call after find() has checked it exists. */
    public function invitee(): User
    {
        return $this->user ?? throw new LogicException('The invited account no longer exists.');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
