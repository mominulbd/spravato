<?php

declare(strict_types=1);

namespace Uptms\Consultations\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The receipt the person gets after sending the qualification form or the
 * self-check. Health privacy: it confirms the request and repeats the
 * reference, never the name, the answers, the topic or the quiz results —
 * so a shared or forwarded mailbox learns nothing about them.
 */
final class ConsultationReceivedNotification extends Notification
{
    public function __construct(
        private readonly string $reference,
        private readonly string $clinicName,
        private readonly string $phone,
        private readonly ?string $hours,
        private readonly string $crisisLine,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('We received your request '.$this->reference)
            ->greeting('Thank you for reaching out.')
            ->line('Your request reached '.$this->clinicName.'. Someone from the team will contact you within one business day during office hours.')
            ->line('Your reference is '.$this->reference.'. Keep it in case you call before you hear from us.');

        $mail->line($this->hours === null
            ? 'To reach the clinic sooner, call '.$this->phone.'.'
            : 'Office hours are '.$this->hours.'. To reach the clinic sooner, call '.$this->phone.'.');

        return $mail
            ->line($this->crisisLine)
            ->line('For your privacy this email repeats nothing you told us. Replies to this address are not read — please call the clinic instead.');
    }
}
