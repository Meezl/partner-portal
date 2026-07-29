<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackSurveyNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Share Your AHAIC Conference Feedback')
            ->greeting('Hello!')
            ->line('Thank you for being a valued AHAIC conference partner!')
            ->line('We would love to hear about your experience. Your feedback helps us improve future conferences and strengthen our partnerships.')
            ->line('Please take a few minutes to complete our feedback survey.')
            ->action('Complete Feedback Survey', url('/partner/feedback'))
            ->line('Your input is greatly appreciated. Thank you for your continued partnership!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'partner_id' => $this->partner->id,
            'organization_name' => $this->partner->organization_name,
        ];
    }
}
