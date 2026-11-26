<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Uptms\Consultations\Models\ConsultationRequest;

/**
 * The request spelled out for the staff alert, used only while Clinic
 * settings → Privacy → "Include the request in the alert" is on. These
 * lines are health information: they go to the clinic's mailbox through
 * the mail provider and are never written to a log or the audit trail.
 */
final readonly class StaffAlertDetails
{
    private const string MISSING = 'Not given';

    /**
     * One "Label: value" line per answer, in the order the detail screen shows them.
     *
     * @return list<string>
     */
    public function lines(ConsultationRequest $request): array
    {
        $lines = [
            'Name: '.$request->name,
            'Phone: '.$request->phone,
            'Email: '.($request->email ?? self::MISSING),
            'Preferred follow-up: '.($request->contact_method?->label() ?? self::MISSING),
            'Best time to reach: '.($request->best_time?->label() ?? self::MISSING),
            'Patient status: '.($request->patient_status?->label() ?? self::MISSING),
            'Asking about: '.$request->topic->shortLabel(),
            'Insurance provider: '.($request->insurance ?? self::MISSING),
        ];

        if ($request->message !== null) {
            $lines[] = 'Message: '.$request->message;
        }

        return $lines;
    }
}
