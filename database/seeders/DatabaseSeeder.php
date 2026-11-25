<?php

declare(strict_types=1);

namespace Database\Seeders;

use Faker\Factory;
use Illuminate\Database\Seeder;
use Uptms\Permissions\Seeders\PermissionSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production seeds roles, permissions and the designed website content
     * (idempotent); the first Super Admin is created with
     * `php artisan uptms:create-super-admin`. Local and e2e databases also
     * get one demo account per role; local ones a sample consultation inbox.
     */
    public function run(): void
    {
        $this->call([PermissionSeeder::class, SiteContentSeeder::class]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([DemoUserSeeder::class]);
        }

        // A sample inbox for reviewing the consultation screens; never in e2e or production.
        // Its factory needs Faker, a dev dependency that a `composer install --no-dev` deploy lacks.
        if (app()->environment('local') && class_exists(Factory::class)) {
            $this->call(DemoConsultationSeeder::class);
        }
    }
}
