<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security confirmation. Sent to the account owner whenever their password
 * changes, so an unauthorised change does not go unnoticed.
 */
class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed')
            ->greeting('Hello '.$notifiable->name)
            ->line('The password on your AHAIC Partner Portal account was just changed.')
            ->line('If this was you, no action is needed.')
            ->line('If it was not you, reset your password immediately and contact us.')
            ->action('Reset your password', url('/forgot-password'));
    }

    public function toArray(object $notifiable): array
    {
        return ['changed_at' => now()->toIso8601String()];
    }
}
