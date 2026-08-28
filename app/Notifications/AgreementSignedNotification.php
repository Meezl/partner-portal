<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the team a partner has signed, which is also the point an invoice
 * becomes outstanding — finance would otherwise have no prompt to chase it.
 */
class AgreementSignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Agreement $agreement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partner = $this->agreement->partner;

        $mail = (new MailMessage)
            ->subject(($partner?->organization_name ?? 'A partner').' has signed their agreement')
            ->greeting('Hello')
            ->line(($partner?->organization_name ?? 'A partner').' has signed their sponsorship agreement.');

        if ($this->agreement->signed_by_name) {
            $mail->line('Signed by: '.$this->agreement->signed_by_name);
        }

        return $mail
            ->line('Their invoice has been generated and is now awaiting payment.')
            ->action('View the partner', url('/admin/partners'));
    }

    public function toArray(object $notifiable): array
    {
        return ['agreement_id' => $this->agreement->id];
    }
}
