<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Permissions\Contracts\HasPermissions;

/**
 * Dashboard numbers (Figma 03): the call-back queue, new requests against
 * the previous period, the daily chart and what people ask about. Always
 * within what the viewer may see.
 */
final readonly class ConsultationOverview
{
    public function __construct(private ConsultationPresenter $presenter) {}

    /** @return array<string, mixed> */
    public function for(HasPermissions&Authenticatable $actor, int $days): array
    {
        $now = now();
        $timezone = (string) config('consultations.timezone');
        $start = $now->copy()->setTimezone($timezone)->startOfDay()->subDays($days - 1)->utc();
        $previousStart = $start->copy()->subDays($days);

        $current = $this->visible($actor)->where('created_at', '>=', $start)->where('status', '!=', ConsultationStatus::Spam)->count();
        $previous = $this->visible($actor)->whereBetween('created_at', [$previousStart, $start])->where('status', '!=', ConsultationStatus::Spam)->count();

        return [
            'range_days' => $days,
            'queue' => $this->queue($actor, $now),
            'new_requests' => [
                'count' => $current,
                'previous' => $previous,
                'change_percent' => $previous === 0 ? null : (int) round(($current - $previous) / $previous * 100),
            ],
            'daily' => $this->daily($actor, $start, $days, $timezone),
            'topics' => $this->topics($actor, $start),
            'recent' => $this->presenter->list($this->visible($actor)->latest('created_at')->latest('id')->limit(5)->get()),
        ];
    }

    /** New requests waiting for a call back, for the sidebar badge. */
    public function waiting(HasPermissions&Authenticatable $actor): int
    {
        return $this->visible($actor)->where('status', ConsultationStatus::New)->count();
    }

    /** @return array<string, mixed> */
    private function queue(HasPermissions&Authenticatable $actor, Carbon $now): array
    {
        $new = fn (): Builder => $this->visible($actor)->where('status', ConsultationStatus::New);

        $longest = $new()->oldest('created_at')->oldest('id')->limit(4)->get()->map(function (ConsultationRequest $request) use ($now): array {
            $waited = (int) $request->created_at->diffInMinutes($now, true);
            $target = $request->follow_up_at === null ? null : max(1, (int) $request->created_at->diffInMinutes($request->follow_up_at, true));

            return [
                'id' => $request->id,
                'reference' => $request->reference,
                'topic' => ['value' => $request->topic->value, 'label' => $request->topic->shortLabel()],
                'waiting_minutes' => $waited,
                'progress' => $target === null ? null : round(min(1, $waited / $target), 3),
                'is_overdue' => $request->isOverdue($now),
            ];
        });

        return [
            'waiting' => $new()->count(),
            'waiting_over_day' => $new()->where('created_at', '<', $now->copy()->subDay())->count(),
            'overdue' => $new()->where('follow_up_at', '<', $now)->count(),
            'longest' => array_values($longest->all()),
        ];
    }

    /** @return list<array{date: string, count: int}> */
    private function daily(HasPermissions&Authenticatable $actor, Carbon $start, int $days, string $timezone): array
    {
        $counts = [];

        foreach ($this->visible($actor)->where('created_at', '>=', $start)->where('status', '!=', ConsultationStatus::Spam)->pluck('created_at') as $createdAt) {
            $date = Carbon::parse($createdAt)->setTimezone($timezone)->toDateString();
            $counts[$date] = ($counts[$date] ?? 0) + 1;
        }

        $series = [];
        $day = $start->copy()->setTimezone($timezone);

        for ($i = 0; $i < $days; $i++) {
            $date = $day->toDateString();
            $series[] = ['date' => $date, 'count' => $counts[$date] ?? 0];
            $day->addDay();
        }

        return $series;
    }

    /** @return list<array{value: string, label: string, count: int, percent: int}> */
    private function topics(HasPermissions&Authenticatable $actor, Carbon $start): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->visible($actor)
            ->where('created_at', '>=', $start)
            ->where('status', '!=', ConsultationStatus::Spam)
            ->selectRaw('topic, count(*) as aggregate')
            ->groupBy('topic')
            ->pluck('aggregate', 'topic')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
        $total = array_sum($counts);

        $topics = array_map(fn (ConsultationTopic $topic): array => [
            'value' => $topic->value,
            'label' => $topic->shortLabel(),
            'count' => $counts[$topic->value] ?? 0,
            'percent' => $total === 0 ? 0 : (int) round(($counts[$topic->value] ?? 0) / $total * 100),
        ], ConsultationTopic::cases());

        usort($topics, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $topics;
    }

    /** @return Builder<ConsultationRequest> */
    private function visible(HasPermissions&Authenticatable $actor): Builder
    {
        return ConsultationRequest::query()->visibleTo($actor);
    }
}
