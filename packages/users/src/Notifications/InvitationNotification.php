<?php

declare(strict_types=1);

namespace Uptms\Users\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** The emailed invitation link. Carries no patient data. */
final class InvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $inviterName,
        private readonly Carbon $expiresAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are invited to the UPTMS admin')
            ->greeting('Hello,')
            ->line("{$this->inviterName} invited you to the University Place TMS Clinic website admin.")
            ->action('Accept invitation', self::url($this->token))
            ->line('The link works once and expires '.$this->expiresAt->toDayDateTimeString().' UTC.')
            ->line('After choosing a password you will set up two-factor authentication.');
    }

    public static function url(string $token): string
    {
        return rtrim((string) config('app.url'), '/').'/admin/invitation/'.$token;
    }
}
