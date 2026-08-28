<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security warning sent to the *previous* address when an account's email is
 * changed. Without it, changing the email silently cuts the original owner off
 * from their own account.
 */
class EmailChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $previousEmail,
        protected string $newEmail,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('The email address on your account was changed')
            ->greeting('Hello')
            ->line('The email address on your AHAIC Partner Portal account was changed from '
                .$this->previousEmail.' to '.$this->newEmail.'.')
            ->line('If you made this change, no action is needed.')
            ->line('If you did not, contact us immediately — you may no longer be able to sign in.')
            ->action('Contact the AHAIC team', 'mailto:'.config('ahaic.central_email'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'previous_email' => $this->previousEmail,
            'new_email' => $this->newEmail,
        ];
    }
}
