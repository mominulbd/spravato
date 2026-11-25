<?php

declare(strict_types=1);

namespace Uptms\Settings\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Publishing\Events\ContentPublished;
use Uptms\Settings\Models\BusinessClosure;
use Uptms\Settings\Models\BusinessHour;

/**
 * Weekly hours and closures, plus the summaries the website prints:
 * "Mon–Fri, 8am–5pm" (utility bar) and "Mon–Fri · 8:00 AM–5:00 PM" (menu).
 */
class OfficeHours
{
    private const array DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @return list<array{day: int, name: string, is_open: bool, opens_at: string|null, closes_at: string|null}>
     */
    public function week(): array
    {
        $rows = BusinessHour::query()->get()->keyBy('day_of_week');
        $week = [];

        foreach (self::DAYS as $day => $name) {
            $row = $rows->get($day);
            $week[] = [
                'day' => $day,
                'name' => $name,
                'is_open' => $row instanceof BusinessHour && $row->is_open,
                'opens_at' => $row instanceof BusinessHour && $row->opens_at !== null ? substr($row->opens_at, 0, 5) : null,
                'closes_at' => $row instanceof BusinessHour && $row->closes_at !== null ? substr($row->closes_at, 0, 5) : null,
            ];
        }

        return $week;
    }

    /**
     * @return list<array{id: int, date: string, label: string}>
     */
    public function closures(bool $upcomingOnly = false): array
    {
        return array_values(BusinessClosure::query()
            ->when($upcomingOnly, fn ($q) => $q->whereDate('date', '>=', today()))
            ->orderBy('date')
            ->get()
            ->map(fn (BusinessClosure $closure): array => ['id' => $closure->id, 'date' => $closure->date->toDateString(), 'label' => $closure->label])
            ->all());
    }

    /**
     * @param  list<array{day: int, is_open: bool, opens_at?: string|null, closes_at?: string|null}>  $days
     * @param  list<array{date: string, label: string}>  $closures
     */
    public function update(array $days, array $closures): void
    {
        $before = ['week' => $this->week(), 'closures' => $this->closures()];

        DB::transaction(function () use ($days, $closures, $before): void {
            foreach ($days as $day) {
                BusinessHour::query()->updateOrCreate(['day_of_week' => $day['day']], [
                    'is_open' => $day['is_open'],
                    'opens_at' => $day['is_open'] ? $day['opens_at'] ?? null : null,
                    'closes_at' => $day['is_open'] ? $day['closes_at'] ?? null : null,
                ]);
            }

            BusinessClosure::query()->delete();

            foreach ($closures as $closure) {
                BusinessClosure::query()->create(['date' => $closure['date'], 'label' => $closure['label']]);
            }

            $this->audit->record('settings.updated', null, $before, ['week' => $this->week(), 'closures' => $this->closures()], label: 'hours');
        });

        ContentPublished::dispatch(['content', 'settings']);
    }

    /** "Mon–Fri, 8am–5pm" */
    public function short(): string
    {
        return $this->summarise(fn (string $time): string => $this->format($time, short: true), ', ', 'Closed');
    }

    /** "Mon–Fri · 8:00 AM–5:00 PM" */
    public function long(): string
    {
        return $this->summarise(fn (string $time): string => $this->format($time, short: false), ' · ', 'Closed');
    }

    /** "8:00 AM – 5:00 PM" for one day of the hours table. */
    public function display(?string $opens, ?string $closes): ?string
    {
        return $opens === null || $closes === null ? null : $this->format($opens, false).' – '.$this->format($closes, false);
    }

    /**
     * Groups consecutive open days with identical hours: Mon–Fri, Sat.
     *
     * @param  callable(string): string  $time
     */
    private function summarise(callable $time, string $separator, string $closed): string
    {
        $groups = [];

        foreach ($this->week() as $day) {
            if (! $day['is_open'] || $day['opens_at'] === null || $day['closes_at'] === null) {
                continue;
            }

            $hours = $time($day['opens_at']).'–'.$time($day['closes_at']);
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['hours'] === $hours && $groups[$last]['to'] === $day['day'] - 1) {
                $groups[$last]['to'] = $day['day'];
            } else {
                $groups[] = ['from' => $day['day'], 'to' => $day['day'], 'hours' => $hours];
            }
        }

        if ($groups === []) {
            return $closed;
        }

        return implode('; ', array_map(function (array $group) use ($separator): string {
            $from = substr(self::DAYS[$group['from']], 0, 3);
            $days = $group['from'] === $group['to'] ? $from : $from.'–'.substr(self::DAYS[$group['to']], 0, 3);

            return $days.$separator.$group['hours'];
        }, $groups));
    }

    private function format(string $time, bool $short): string
    {
        $carbon = Carbon::createFromFormat('H:i', substr($time, 0, 5));

        if (! $carbon instanceof Carbon) {
            return $time;
        }

        if ($short) {
            return $carbon->minute === 0 ? $carbon->format('ga') : $carbon->format('g:ia');
        }

        return $carbon->format('g:i A');
    }
}
