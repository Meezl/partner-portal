<?php

namespace App\Notifications;

use App\Models\ConferenceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a partner the outcome of the programme team's review of their session.
 */
class SessionReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ConferenceSession $session,
        protected bool $approved,
        protected ?string $notes = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->approved ? $this->approvedMail() : $this->returnedMail();
    }

    private function approvedMail(): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Session approved: {$this->session->title}")
            ->greeting('Good news')
            ->line("Your session \"{$this->session->title}\" has been approved for the programme.");

        if ($slot = $this->session->sessionSlot) {
            $mail->line("Scheduled for {$slot->scheduleLabel()}.");
        }

        if ($this->notes) {
            $mail->line('Note from the partnerships team: '.$this->notes);
        }

        return $mail
            ->action('View your sessions', url('/partner/sessions'))
            ->line('No further action is needed from you.');
    }

    private function returnedMail(): MailMessage
    {
        return (new MailMessage)
            ->subject("Action needed: {$this->session->title}")
            ->greeting('Hello')
            ->line("Your session \"{$this->session->title}\" has been sent back for revision, so it is a draft again.")
            ->line('What the partnerships team asked for: '.$this->notes)
            ->action('Update your session', url('/partner/sessions'))
            ->line('Please make the changes and submit it again.');
    }
}
