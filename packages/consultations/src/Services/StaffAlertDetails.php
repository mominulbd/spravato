<?php

declare(strict_types=1);

namespace Uptms\Consultations\Services;

use Uptms\Consultations\Domain\Enums\PriorTreatment;
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
        $treatment = $request->prior_treatment === null ? null : PriorTreatment::tryFrom($request->prior_treatment);

        $lines = [
            'Name: '.$request->name,
            'Phone: '.$request->phone,
            'Email: '.($request->email ?? self::MISSING),
            'Preferred contact: '.($request->contact_method?->label() ?? self::MISSING),
            'Best time to call: '.($request->best_time?->label() ?? self::MISSING),
            'Asking about: '.$request->topic->shortLabel(),
            'Insurance: '.($request->insurance ?? self::MISSING),
            'Therapy or antidepressants tried: '.($treatment?->label() ?? self::MISSING),
        ];

        if ($request->referred_by !== null) {
            $lines[] = 'Referred by: '.$request->referred_by;
        }

        $scores = ConsultationPresenter::quizScores($request->quiz_results);

        if ($scores !== '') {
            $crisis = (bool) ($request->quiz_results['crisis'] ?? false);
            $lines[] = 'Self-check: '.$scores.($crisis ? ' · flagged a crisis answer' : '');
        }

        if ($request->message !== null) {
            $lines[] = 'Message: '.$request->message;
        }

        return $lines;
    }
}
