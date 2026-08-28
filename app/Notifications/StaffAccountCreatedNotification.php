<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone an account has been created for them. Deliberately does not
 * carry a password: the recipient sets their own through the reset flow.
 */
class StaffAccountCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected User $creator) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your AHAIC Partner Portal account is ready')
            ->greeting('Hello '.$notifiable->name)
            ->line($this->creator->name.' has created an account for you on the AHAIC Partner Portal.')
            ->line('Your role is: '.($notifiable->role?->label() ?? 'Team member').'.')
            ->line('Use the link below to set your password and sign in.')
            ->action('Set your password', url('/forgot-password'))
            ->line('If you were not expecting this, you can ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return ['created_by' => $this->creator->id];
    }
}
