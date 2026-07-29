<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionLockedNotification extends Notification
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
            ->subject('Submission Received - AHAIC Partner Portal')
            ->greeting('Hello!')
            ->line('We have received all your submitted materials for the AHAIC conference.')
            ->line('Your submission has been locked and is now under review by the AHAIC team.')
            ->line('If you need to make any changes, please submit a change request through the partner portal.')
            ->action('View Your Dashboard', url('/partner/dashboard'))
            ->line('You will be notified once your sessions have been scheduled. Thank you for your partnership!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'partner_id' => $this->partner->id,
            'organization_name' => $this->partner->organization_name,
        ];
    }
}
