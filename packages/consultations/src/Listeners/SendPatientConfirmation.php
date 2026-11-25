<?php

declare(strict_types=1);

namespace Uptms\Consultations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Events\ConsultationRequestSubmitted;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Notifications\ConsultationReceivedNotification;
use Uptms\Settings\Services\OfficeHours;
use Uptms\Settings\Services\Settings;

/**
 * Emails the person a receipt for their own request on the queue, then
 * notes in the request's activity that it went out. The mail carries the
 * reference and the clinic's details only, never what they typed.
 */
final class SendPatientConfirmation implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public bool $afterCommit = true;

    public function __construct(
        private readonly Settings $settings,
        private readonly OfficeHours $hours,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(ConsultationRequestSubmitted $event): void
    {
        $request = ConsultationRequest::query()->find($event->requestId);

        if (! $request instanceof ConsultationRequest || $request->reference === null || $request->email === null) {
            return;
        }

        $profile = $this->settings->group('profile');
        // "Closed" means no weekly hours are set: then the mail names no hours at all.
        $hours = $this->hours->long();

        Notification::route('mail', $request->email)->notifyNow(new ConsultationReceivedNotification(
            $request->reference,
            (string) $profile['name'],
            (string) $profile['phone'],
            $hours === 'Closed' || $hours === '' ? null : $hours,
            (string) $this->settings->get('disclaimers.crisis_short'),
        ));

        $this->audit->record('consultation.confirmation_sent', $request);
    }
}
