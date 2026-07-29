<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgreementReadyNotification extends Notification
{
    use Queueable;

    protected $agreement;

    public function __construct(Agreement $agreement)
    {
        $this->agreement = $agreement;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/partner/agreement');

        return (new MailMessage)
            ->subject('Your AHAIC 2027 Sponsorship Agreement is Ready')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your sponsorship agreement for AHAIC 2027 has been generated successfully after your package confirmation.')
            ->line('Please review the agreement, then either digitally sign it in the portal or upload a signed PDF.')
            ->action('View Agreement', $url)
            ->line('Thank you for your partnership with us!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'agreement_id' => $this->agreement->id,
        ];
    }
}
