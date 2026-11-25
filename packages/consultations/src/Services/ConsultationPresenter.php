<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Illuminate\Support\Carbon;
use Uptms\AuditLog\Services\AuditTrail;
use Uptms\Consultations\Domain\Enums\PriorTreatment;
use Uptms\Consultations\Models\ConsultationNote;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Settings\Services\Settings;
use Uptms\Users\Services\StaffDirectory;

/**
 * What the admin sees. Lists show masked contact details only ("Daniel R.
 * · (253) •••-0198"); the full details appear on the detail screen, whose
 * every opening is audited.
 */
final readonly class ConsultationPresenter
{
    /** Audit actions shown in the Activity card, with their wording. */
    private const array ACTIVITY = [
        'consultation.submitted' => 'Submitted',
        'consultation.alert_sent' => 'Staff alert emailed',
        'consultation.confirmation_sent' => 'Confirmation emailed to the patient',
        'consultation.viewed' => 'Viewed',
        'consultation.status_changed' => 'Status changed',
        'consultation.assigned' => 'Assigned',
        'consultation.follow_up_changed' => 'Follow-up changed',
        'consultation.note_added' => 'Note added',
    ];

    public function __construct(
        private StaffDirectory $staff,
        private AuditTrail $trail,
        private Settings $settings,
        private SourceDescriber $describer,
    ) {}

    /**
     * @param  iterable<ConsultationRequest>  $requests
     * @return list<array<string, mixed>>
     */
    public function list(iterable $requests): array
    {
        $requests = collect($requests);
        $names = $this->staff->names(array_values(array_filter($requests->pluck('assigned_to')->all(), 'is_int')));
        $now = now();

        return array_values($requests->map(fn (ConsultationRequest $request): array => [
            ...$this->summary($request, $names, $now),
            'contact' => self::maskName($request->name).' · '.self::maskPhone($request->phone),
        ])->all());
    }

    /** @return array<string, mixed> */
    public function detail(ConsultationRequest $request): array
    {
        $request->loadMissing('notes');
        $activity = $this->trail->for($request, 30, array_keys(self::ACTIVITY));
        $ids = [
            ...array_filter([$request->assigned_to], 'is_int'),
            ...array_values(array_filter($request->notes->pluck('user_id')->all(), 'is_int')),
            ...$this->assigneesIn($activity),
        ];
        $names = $this->staff->names($ids);
        $retention = (int) $this->settings->get('privacy.consultation_retention_days');

        return [
            ...$this->summary($request, $names, now()),
            'name' => $request->name,
            'phone' => $request->phone,
            'phone_href' => 'tel:+1'.substr(preg_replace('/\D/', '', $request->phone) ?? '', -10),
            'email' => $request->email,
            'message' => $request->message,
            'referred_by' => $request->referred_by,
            'best_time' => $request->best_time === null ? null : ['value' => $request->best_time->value, 'label' => $request->best_time->label()],
            'insurance' => $request->insurance,
            'prior_treatment' => self::priorTreatment($request->prior_treatment),
            'contact_method' => $request->contact_method === null ? null : ['value' => $request->contact_method->value, 'label' => $request->contact_method->label()],
            'quiz' => self::quiz($request->quiz_results),
            'source' => [
                'page' => $request->source_page,
                'previous_page' => $request->previous_page,
                'first_touch' => $this->describer->firstTouch($request->referrer_host, $request->utm),
                'utm' => $request->utm,
                'device' => $request->device,
            ],
            'privacy' => [
                'view_count' => $this->trail->count($request, 'consultation.viewed'),
                'deletes_on' => $request->created_at->copy()->addDays($retention)->toDateString(),
                'retention_days' => $retention,
            ],
            'notes' => array_values($request->notes->map(fn (ConsultationNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'author' => $note->user_id === null ? null : ($names[$note->user_id] ?? null),
                'created_at' => $note->created_at->toIso8601String(),
            ])->all()),
            'activity' => array_map(fn (array $entry): array => [
                'id' => $entry['id'],
                'action' => $entry['action'],
                'label' => $this->activityLabel($entry, $names),
                'user' => $entry['user'],
                'created_at' => $entry['created_at'],
            ], $activity),
        ];
    }

    /**
     * @param  array<int, array{id: int, name: string, initials: string}>  $names
     * @return array<string, mixed>
     */
    private function summary(ConsultationRequest $request, array $names, Carbon $now): array
    {
        $waiting = $request->status->value === 'new' ? (int) $request->created_at->diffInMinutes($now, true) : null;

        return [
            'id' => $request->id,
            'reference' => $request->reference,
            'topic' => ['value' => $request->topic->value, 'label' => $request->topic->shortLabel()],
            'origin' => $request->origin->value,
            'status' => $request->status->value,
            'is_overdue' => $request->isOverdue($now),
            'waiting_minutes' => $waiting,
            'assigned_to' => $request->assigned_to === null ? null : ($names[$request->assigned_to] ?? null),
            'follow_up_at' => $request->follow_up_at?->toIso8601String(),
            'received_at' => $request->created_at->toIso8601String(),
            'contacted_at' => $request->contacted_at?->toIso8601String(),
            'scheduled_at' => $request->scheduled_at?->toIso8601String(),
            'closed_at' => $request->closed_at?->toIso8601String(),
            'source_page' => $request->source_page,
            'allowed_statuses' => array_map(fn ($status): string => $status->value, $request->status->allowedTransitions()),
        ];
    }

    /**
     * The self-check results as staff read them (docs/QUIZ.md § Admin).
     *
     * @param  array<string, mixed>|null  $results
     * @return array<string, mixed>|null
     */
    public static function quiz(?array $results): ?array
    {
        if ($results === null) {
            return null;
        }

        $lines = fn (mixed $rows): array => array_values(array_map(fn (array $row): array => ['prompt' => (string) $row['prompt'], 'answer' => (string) $row['answer']], (array) $rows));

        return [
            'submitted_at' => $results['submitted_at'] ?? null,
            'crisis' => (bool) ($results['crisis'] ?? false),
            'scales' => array_values(array_map(fn (array $scale): array => [
                'key' => (string) $scale['key'],
                'label' => (string) $scale['label'],
                'instrument' => (string) ($scale['instrument'] ?? ''),
                'score' => (int) $scale['score'],
                'max' => (int) $scale['max'],
                'band' => is_array($scale['band'] ?? null) ? (string) $scale['band']['title'] : null,
            ], (array) ($results['scales'] ?? []))),
            'answers' => $lines($results['answers'] ?? []),
            'feedback' => ($results['feedback'] ?? null) === null ? null : $lines($results['feedback']),
        ];
    }

    /**
     * "PHQ-9 9/27 · GAD-7 14/21", for the export.
     *
     * @param  array<string, mixed>|null  $results
     */
    public static function quizScores(?array $results): string
    {
        $quiz = self::quiz($results);

        return $quiz === null ? '' : implode(' · ', array_map(fn (array $scale): string => ($scale['instrument'] ?: $scale['label']).' '.$scale['score'].'/'.$scale['max'], $quiz['scales']));
    }

    /** @return array{value: string, label: string}|null */
    public static function priorTreatment(?string $value): ?array
    {
        $treatment = $value === null ? null : PriorTreatment::tryFrom($value);

        return $treatment === null ? null : ['value' => $treatment->value, 'label' => $treatment->label()];
    }

    /** "Daniel Reyes" → "Daniel R." */
    public static function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? '';

        if (count($parts) < 2) {
            return $first;
        }

        return $first.' '.mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)).'.';
    }

    /** "253-555-0198" → "(253) •••-0198" */
    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $digits = strlen($digits) > 10 ? substr($digits, -10) : $digits;

        if (strlen($digits) < 10) {
            return '•••-'.substr($digits, -4);
        }

        return '('.substr($digits, 0, 3).') •••-'.substr($digits, -4);
    }

    /**
     * @param  list<array{action: string, new_values: array<string, mixed>|null}>  $activity
     * @return list<int>
     */
    private function assigneesIn(array $activity): array
    {
        $ids = [];

        foreach ($activity as $entry) {
            $id = $entry['new_values']['assigned_to'] ?? null;

            if (is_int($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array{action: string, user: array{id: int, name: string}|null, new_values: array<string, mixed>|null}  $entry
     * @param  array<int, array{id: int, name: string, initials: string}>  $names
     */
    private function activityLabel(array $entry, array $names): string
    {
        $who = $entry['user']['name'] ?? null;
        $new = $entry['new_values'] ?? [];

        return match ($entry['action']) {
            'consultation.submitted' => 'Submitted'.(is_string($new['source_page'] ?? null) ? ' from '.$new['source_page'] : ''),
            'consultation.alert_sent' => 'Staff alert emailed'.(($new['details'] ?? false) === true ? ' with the request' : ' (link only)'),
            'consultation.viewed' => 'Viewed'.($who !== null ? ' by '.$who : ''),
            'consultation.status_changed' => 'Marked '.mb_strtolower((string) ($new['status'] ?? '')).($who !== null ? ' by '.$who : ''),
            'consultation.assigned' => is_int($new['assigned_to'] ?? null)
                ? 'Assigned to '.($names[$new['assigned_to']]['name'] ?? 'a former teammate').($who !== null ? ' by '.$who : '')
                : 'Unassigned'.($who !== null ? ' by '.$who : ''),
            'consultation.follow_up_changed' => 'Follow-up changed'.($who !== null ? ' by '.$who : ''),
            'consultation.note_added' => 'Note added'.($who !== null ? ' by '.$who : ''),
            default => self::ACTIVITY[$entry['action']] ?? $entry['action'],
        };
    }
}
