<?php

declare(strict_types=1);

namespace Uptms\Users\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Uptms\AuditLog\Concerns\Auditable;
use Uptms\Permissions\Concerns\HasRole;
use Uptms\Permissions\Contracts\HasPermissions;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Domain\Enums\UserStatus;
use Uptms\Users\Factories\UserFactory;

/**
 * A staff account. Signs in with email, password and (when the role
 * requires it) a TOTP code.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $credentials
 * @property string $email
 * @property string|null $password
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Role|null $role
 * @property-read Collection<int, UserInvitation> $invitations
 */
class User extends Authenticatable implements HasPermissions
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRole;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = ['name', 'credentials', 'email'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** @var list<string> */
    protected array $auditExclude = ['last_login_at', 'last_seen_at', 'email_verified_at', 'uuid'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** @return HasMany<UserInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function status(): UserStatus
    {
        return match (true) {
            ! $this->is_active => UserStatus::Deactivated,
            $this->password === null => UserStatus::Invited,
            default => UserStatus::Active,
        };
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** The role says this account must confirm TOTP before using the admin. */
    public function requiresTwoFactor(): bool
    {
        $this->loadMissing('role');
        $role = $this->getRelationValue('role');

        // No role means no access anyway; stay on the safe side.
        return ! $role instanceof Role || $role->requires_two_factor;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithStatus(Builder $query, UserStatus $status): void
    {
        match ($status) {
            UserStatus::Deactivated => $query->where('is_active', false),
            UserStatus::Invited => $query->where('is_active', true)->whereNull('password'),
            UserStatus::Active => $query->where('is_active', true)->whereNotNull('password'),
        };
    }
}
