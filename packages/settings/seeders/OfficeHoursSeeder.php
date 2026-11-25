<?php

declare(strict_types=1);

namespace Uptms\Settings\Seeders;

use Illuminate\Database\Seeder;
use Uptms\Settings\Models\BusinessHour;

/** Mon–Fri 8:00–17:00 (Figma 24). Idempotent: existing rows are kept. */
class OfficeHoursSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 7) as $day) {
            BusinessHour::query()->firstOrCreate(['day_of_week' => $day], [
                'is_open' => $day <= 5,
                'opens_at' => $day <= 5 ? '08:00' : null,
                'closes_at' => $day <= 5 ? '17:00' : null,
            ]);
        }
    }
}
