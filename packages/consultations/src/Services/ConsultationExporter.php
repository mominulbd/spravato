<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Users\Services\StaffDirectory;

/**
 * CSV of the filtered inbox, decrypted, for the clinic's own records.
 * Streamed in chunks; cells that a spreadsheet would run as a formula are
 * prefixed with a quote.
 */
final readonly class ConsultationExporter
{
    public const int MAX_ROWS = 50000;

    private const array HEADER = [
        'Reference', 'Received', 'Status', 'Topic', 'Name', 'Phone', 'Email', 'Insurance provider', 'Patient status',
        'Preferred follow-up', 'Best time to reach', 'Not-an-emergency acknowledged', 'Button', 'Origin',
        'Message', 'Assigned to', 'Follow-up by', 'Contacted', 'Scheduled', 'Closed', 'Page',
    ];

    public function __construct(private StaffDirectory $staff) {}

    /** @param Builder<ConsultationRequest> $query */
    public function stream(Builder $query, string $filename): StreamedResponse
    {
        $timezone = (string) config('consultations.timezone');

        return new StreamedResponse(function () use ($query, $timezone): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::HEADER, escape: '');
            $written = 0;

            $query->orderBy('id')->chunkById(500, function ($requests) use ($out, $timezone, &$written): bool {
                $names = $this->staff->names(array_values(array_filter($requests->pluck('assigned_to')->all(), 'is_int')));

                foreach ($requests as $request) {
                    /** @var ConsultationRequest $request */
                    fputcsv($out, array_map(self::safe(...), [
                        $request->reference,
                        $this->time($request->created_at, $timezone),
                        $request->status->label(),
                        $request->topic->label(),
                        $request->name,
                        $request->phone,
                        $request->email,
                        $request->insurance,
                        $request->patient_status?->label(),
                        $request->contact_method?->label(),
                        $request->best_time?->label(),
                        $request->emergency_acknowledged ? 'Yes' : 'No',
                        $request->cta,
                        $request->origin->label(),
                        $request->message,
                        $request->assigned_to === null ? '' : ($names[$request->assigned_to]['name'] ?? ''),
                        $this->time($request->follow_up_at, $timezone),
                        $this->time($request->contacted_at, $timezone),
                        $this->time($request->scheduled_at, $timezone),
                        $this->time($request->closed_at, $timezone),
                        $request->source_page,
                    ]), escape: '');

                    if (++$written >= self::MAX_ROWS) {
                        return false;
                    }
                }

                return true;
            });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function time(?Carbon $time, string $timezone): string
    {
        return $time === null ? '' : $time->copy()->setTimezone($timezone)->format('Y-m-d H:i');
    }

    public static function safe(mixed $value): string
    {
        $value = $value === null ? '' : (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
