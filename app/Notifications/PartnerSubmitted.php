<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PartnerSubmitted extends Notification
{
    public function __construct(
        protected Partner $partner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New partner submission received')
            ->greeting('Hello!')
            ->line('A partner submission has been completed in the AHAIC portal.')
            ->line('Organization: '.$this->partner->organization_name)
            ->action('Review Partner', url('/admin/partners/'.$this->partner->id));
    }
}
