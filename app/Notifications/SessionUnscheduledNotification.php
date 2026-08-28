<?php

namespace App\Notifications;

use App\Models\ConferenceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the programme team removes a session's room booking. The partner
 * previously had a confirmed room and time, so silently taking it away would
 * leave them turning up to a slot that no longer exists.
 */
class SessionUnscheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ConferenceSession $session,
        protected ?string $previousPlacement = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Schedule change: '.$this->session->title)
            ->greeting('Hello')
            ->line("The room and time for your session \"{$this->session->title}\" have been removed while the programme team rework the schedule.");

        if ($this->previousPlacement) {
            $mail->line('It was previously scheduled for '.$this->previousPlacement.'.');
        }

        return $mail
            ->line('The session itself is unchanged — only its placement has been cleared.')
            ->action('View your sessions', url('/partner/sessions'))
            ->line('We will let you know as soon as it has been rescheduled.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'session_id' => $this->session->id,
            'previous_placement' => $this->previousPlacement,
        ];
    }
}
