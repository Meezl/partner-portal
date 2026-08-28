<?php

namespace App\Notifications;

use App\Models\Conference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells partners the agenda is live, so they can check where their sessions
 * ended up.
 */
class AgendaPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Conference $conference) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('The '.$this->conference->name.' agenda is now published')
            ->greeting('Hello')
            ->line('The conference agenda has been published.')
            ->line('You can now see the confirmed room and time for each of your sessions.')
            ->action('View your schedule', url('/partner/schedule'))
            ->line('If anything looks wrong, raise a change request in the portal and our team will review it.');
    }

    public function toArray(object $notifiable): array
    {
        return ['conference_id' => $this->conference->id];
    }
}
