<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterestRejectedNotification extends Notification
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
            ->subject('Update on your AHAIC expression of interest')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your expression of interest was reviewed by the AHAIC partnerships team and is not approved yet.')
            ->line('You can return to the portal to review and update your submission if you would like to resubmit it.')
            ->action('Review Expression of Interest', url('/partner/expression-of-interest'))
            ->line('Please contact '.config('ahaic.central_email', 'info@ahaic.org').' if you need support.');
    }
}
