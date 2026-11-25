<?php

declare(strict_types=1);

namespace Uptms\Consultations\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Events\ConsultationRequestSubmitted;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Notifications\NewConsultationRequestNotification;
use Uptms\Consultations\Services\StaffAlertDetails;
use Uptms\Settings\Services\Settings;

/**
 * Emails the clinic's alert address (Clinic settings → Privacy) on the
 * queue, then notes in the request's activity that the alert went out and
 * whether it carried the request or a link only. The activity note records
 * the choice, never the answers themselves.
 */
final class SendStaffAlert implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public bool $afterCommit = true;

    public function __construct(
        private readonly Settings $settings,
        private readonly StaffAlertDetails $details,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(ConsultationRequestSubmitted $event): void
    {
        $request = ConsultationRequest::query()->find($event->requestId);
        $email = $this->settings->get('privacy.staff_notification_email');

        if (! $request instanceof ConsultationRequest || $request->reference === null || ! is_string($email) || $email === '') {
            return;
        }

        $details = $this->settings->get('privacy.staff_alert_details') === true ? $this->details->lines($request) : [];

        Notification::route('mail', $email)->notifyNow(new NewConsultationRequestNotification($request->id, $request->reference, $details));
        $this->audit->record('consultation.alert_sent', $request, null, ['details' => $details !== []]);
    }
}
