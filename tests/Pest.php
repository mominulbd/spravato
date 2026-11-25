<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Uptms\Permissions\Seeders\PermissionSeeder;
use Uptms\Users\Models\User;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Tests live beside the package they cover: packages/<module>/tests.
| Feature tests run against MySQL (uptms_test) — the schema relies on JSON
| columns and fulltext indexes, which SQLite models differently. Unit tests
| for framework-free code need no database.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => test()->seed(PermissionSeeder::class))
    ->in('Feature', '../packages/*/tests/Feature');

pest()->extend(TestCase::class)
    ->in('Unit', '../packages/*/tests/Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A staff user holding a seeded role (super_admin, admin, manager, staff),
 * password "password", 2FA already confirmed unless `$twoFactor` is false.
 *
 * @param  array<string, mixed>  $attributes
 */
function staffUser(string $role = 'staff', array $attributes = [], bool $twoFactor = true): User
{
    $factory = User::factory()->withRole($role);

    return ($twoFactor ? $factory->withTwoFactor() : $factory)->create($attributes);
}

function superAdmin(): User
{
    return staffUser('super_admin');
}

/** Act as a signed-in user for the next requests. */
function asAdmin(User $user): TestCase
{
    app('auth')->forgetGuards();

    /** @var TestCase $test */
    $test = test();

    return $test->actingAs($user);
}

/** Assert the standard error envelope with a stable code (docs/API.md §1). */
function assertErrorCode(TestResponse $response, string $code, int $status): TestResponse
{
    return $response->assertStatus($status)->assertJson(['success' => false, 'error_code' => $code]);
}
