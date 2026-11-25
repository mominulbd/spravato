<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Illuminate\Support\Carbon;
use Uptms\Settings\Services\OfficeHours;

/**
 * The call-back target (Figma 03/05: "Target · 1 business day"): closing
 * time of the next business day after the request arrived, skipping days
 * the clinic is closed. Reckoned in the clinic's time zone, stored in UTC.
 */
final readonly class FollowUpClock
{
    public function __construct(private OfficeHours $hours) {}

    public function dueFor(Carbon $receivedAt): Carbon
    {
        $timezone = (string) config('consultations.timezone');
        $days = max(1, (int) config('consultations.follow_up_business_days'));
        $week = collect($this->hours->week())->keyBy('day');
        $closed = array_column($this->hours->closures(), 'date');
        $day = $receivedAt->copy()->setTimezone($timezone)->startOfDay();

        // A clinic with no open days configured still gets a target.
        if ($week->every(fn (array $d): bool => ! $d['is_open'] || $d['closes_at'] === null)) {
            return $receivedAt->copy()->addDays($days)->utc();
        }

        for ($guard = 0; $guard < 60; $guard++) {
            $day->addDay();
            $hours = $week->get($day->isoWeekday());

            if ($hours === null || ! $hours['is_open'] || $hours['closes_at'] === null || in_array($day->toDateString(), $closed, true)) {
                continue;
            }

            if (--$days === 0) {
                [$hour, $minute] = array_map('intval', explode(':', $hours['closes_at']));

                return $day->copy()->setTime($hour, $minute)->utc();
            }
        }

        return $receivedAt->copy()->addDays($days)->utc();
    }
}
