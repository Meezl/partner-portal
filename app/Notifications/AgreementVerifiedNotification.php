<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Closes the loop on a signed agreement: the partner otherwise has no way of
 * knowing the team accepted it.
 */
class AgreementVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Agreement $agreement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your partnership agreement has been verified')
            ->greeting('Hello')
            ->line('The AHAIC partnerships team has reviewed and verified your signed partnership agreement.')
            ->line('No further action is needed on the agreement. You can download your signed copy at any time.')
            ->action('View your agreement', url('/partner/agreement'));
    }

    public function toArray(object $notifiable): array
    {
        return ['agreement_id' => $this->agreement->id];
    }
}
