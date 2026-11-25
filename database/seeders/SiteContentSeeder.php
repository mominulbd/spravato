<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Content\CollectionsContentSeeder;
use Database\Seeders\Content\MenusContentSeeder;
use Database\Seeders\Content\PagesContentSeeder;
use Database\Seeders\Content\SeoContentSeeder;
use Database\Seeders\Content\SiteMediaSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Publishing\Services\PublicCache;
use Uptms\Settings\Seeders\OfficeHoursSeeder;

/**
 * Every piece of website copy from the Figma design (docs/ROADMAP.md,
 * Phase 2), so a fresh install is the designed site. Each part is
 * idempotent: it only fills empty tables, never overwrites edits.
 * Variants are generated inline (sync queue) so seeded images are ready.
 */
class SiteContentSeeder extends Seeder
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly PublicCache $cache,
    ) {}

    public function run(): void
    {
        $queue = Config::get('queue.default');
        $secret = Config::get('services.frontend.revalidate_secret');
        Config::set('queue.default', 'sync');
        // Seeding publishes many entries; the website may not be running yet.
        Config::set('services.frontend.revalidate_secret', null);

        try {
            $this->audit->withoutAuditing(function (): void {
                $this->call([
                    OfficeHoursSeeder::class,
                    SiteMediaSeeder::class,
                    CollectionsContentSeeder::class,
                    PagesContentSeeder::class,
                    MenusContentSeeder::class,
                    SeoContentSeeder::class,
                ]);
            });
        } finally {
            Config::set('queue.default', $queue);
            Config::set('services.frontend.revalidate_secret', $secret);
        }

        $this->cache->flush();
    }
}
