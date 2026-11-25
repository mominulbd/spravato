<?php

declare(strict_types=1);

namespace Uptms\Users\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** Base32 TOTP secret shared by test users with 2FA on. */
    public const string TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXP';

    private static ?string $passwordHash = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$passwordHash ??= Hash::make('password'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /** No password yet: an invitation is pending. */
    public function invited(): static
    {
        return $this->state(fn (): array => ['password' => null]);
    }

    /** TOTP confirmed with TWO_FACTOR_SECRET. */
    public function withTwoFactor(): static
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => self::TWO_FACTOR_SECRET,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /** Give the user a role by slug, creating the role row if the seeder has not run. */
    public function withRole(string $slug): static
    {
        return $this->afterMaking(function (User $user) use ($slug): void {
            $role = Role::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug))],
            );

            $user->forceFill(['role_id' => $role->id]);
        });
    }
}
