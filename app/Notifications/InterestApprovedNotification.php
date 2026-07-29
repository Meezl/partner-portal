<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterestApprovedNotification extends Notification
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
            ->subject('Your AHAIC partnership interest has been approved')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your expression of interest has been approved by the AHAIC partnerships team.')
            ->line('Please confirm your selected sponsorship package and complete your company/legal details, including billing address and tax details.')
            ->action('Confirm Sponsorship Package', url('/partner/commitment'))
            ->line('Once submitted, your partnership agreement will be generated automatically for signing.');
    }
}
