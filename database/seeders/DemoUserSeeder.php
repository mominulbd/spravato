<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Permissions\Models\Role;
use Uptms\Users\Models\User;

/**
 * Local and e2e only (DatabaseSeeder): one account per built-in role, all
 * with the password "Uptms@2026" and no 2FA yet, so the first sign-in walks
 * through 2FA set-up exactly as a real invitee does. Emails match Figma 25.
 */
class DemoUserSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string|null}> email → [name, role, credentials] */
    private const array USERS = [
        'admin@uptms.com' => ['Admin', 'super_admin', 'MSN, PMHNP-BC'],
        'dev@agency.test' => ['Web partner', 'admin', null],
        'cathy@uptms.test' => ['Cathy Mwangi', 'manager', 'BSN, RN'],
        'john@uptms.test' => ['John Kiongo', 'staff', null],
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    public function run(): void
    {
        $this->audit->withoutAuditing(function (): void {
            foreach (self::USERS as $email => [$name, $role, $credentials]) {
                $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);
                $user->fill(['name' => $name, 'credentials' => $credentials]);
                $user->forceFill([
                    'password' => 'Uptms@2026',
                    'role_id' => Role::query()->where('slug', $role)->value('id'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ])->save();
            }
        });
    }
}
