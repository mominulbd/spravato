<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Uptms\Consultations\Services\FollowUpClock;
use Uptms\Settings\Models\BusinessClosure;
use Uptms\Settings\Models\BusinessHour;

beforeEach(function () {
    foreach (range(1, 7) as $day) {
        BusinessHour::query()->create(['day_of_week' => $day, 'is_open' => $day <= 5, 'opens_at' => $day <= 5 ? '08:00' : null, 'closes_at' => $day <= 5 ? '17:00' : null]);
    }
});

it('targets closing time of the next business day in the clinic time zone', function (string $received, string $due) {
    $at = app(FollowUpClock::class)->dueFor(Carbon::parse($received, 'America/Los_Angeles'));

    expect($at->setTimezone('America/Los_Angeles')->format('D Y-m-d H:i'))->toBe($due);
})->with([
    'Monday afternoon' => ['2026-10-05 14:05', 'Tue 2026-10-06 17:00'],
    'Friday evening' => ['2026-10-09 19:30', 'Mon 2026-10-12 17:00'],
    'Saturday' => ['2026-10-10 09:00', 'Mon 2026-10-12 17:00'],
]);

it('skips days the clinic is closed', function () {
    BusinessClosure::query()->create(['date' => '2026-11-26', 'label' => 'Thanksgiving']);

    $at = app(FollowUpClock::class)->dueFor(Carbon::parse('2026-11-25 10:00', 'America/Los_Angeles'));

    expect($at->setTimezone('America/Los_Angeles')->format('Y-m-d H:i'))->toBe('2026-11-27 17:00');
});
