<?php

declare(strict_types=1);

namespace Uptms\Consultations\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The staff alert. It always carries the reference and a link to the
 * request; it carries the person's answers as well only while Clinic
 * settings → Privacy → "Include the request in the alert" is on, which
 * puts health information in the clinic's mailbox (see docs/SETUP.md).
 */
final class NewConsultationRequestNotification extends Notification
{
    /** @param  list<string>  $details  the request as `StaffAlertDetails` spells it out, or [] for a link only */
    public function __construct(
        private readonly int $requestId,
        private readonly string $reference,
        private readonly array $details = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('New consultation request '.$this->reference)
            ->greeting('A new request is waiting.')
            ->line('Someone asked the clinic for a call back through the website.');

        foreach ($this->details as $line) {
            $mail->line($line);
        }

        $mail->action('Open the request', self::url($this->requestId));

        return $mail->line($this->details === []
            ? 'For privacy, the details are shown only after you sign in to the admin.'
            : 'These answers are health information: keep this email inside the clinic mailbox, do not forward it, and work from the admin, where every view is recorded.');
    }

    public static function url(int $requestId): string
    {
        return rtrim((string) config('app.url'), '/').'/admin/consultations/'.$requestId;
    }
}
