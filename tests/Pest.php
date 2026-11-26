<?php

declare(strict_types=1);

use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Uptms\Media\Jobs\GenerateMediaVariants;
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

/**
 * The designed site content without its image variants. Encoding ~27 photos
 * to AVIF/WebP costs ~15 s of CPU per test, and the tests that only need the
 * pages, menus and media rows (SEO, sitemap, redirects) never look at the
 * variants. tests/Feature/SiteContentSeederTest.php still seeds for real.
 */
function seedSiteContent(): void
{
    Bus::fake([GenerateMediaVariants::class]);
    test()->seed(SiteContentSeeder::class);
}
