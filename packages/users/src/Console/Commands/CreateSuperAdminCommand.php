<?php

declare(strict_types=1);

namespace Uptms\Users\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Uptms\Permissions\Models\Role;
use Uptms\Permissions\Services\PermissionRegistry;
use Uptms\Users\Models\User;

#[Signature('uptms:create-super-admin {email} {name}')]
#[Description('Create the first Super Admin (prompts for the password; 2FA is set up at first sign-in)')]
final class CreateSuperAdminCommand extends Command
{
    public function handle(): int
    {
        $role = Role::query()->where('slug', PermissionRegistry::SUPER_ADMIN)->first();

        if ($role === null) {
            $this->components->error('Seed the roles first: php artisan db:seed');

            return self::FAILURE;
        }

        $email = mb_strtolower((string) $this->argument('email'));
        $password = (string) $this->secret('Password (at least 12 characters, letters and numbers)');

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'regex:/[a-zA-Z]/', 'regex:/\d/'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => (string) $this->argument('name'), 'email' => $email]);
        $user->forceFill(['password' => $password, 'role_id' => $role->id, 'is_active' => true, 'email_verified_at' => now()])->save();

        $this->components->info("Super Admin {$email} created.");

        return self::SUCCESS;
    }
}
