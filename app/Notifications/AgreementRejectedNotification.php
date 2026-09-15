<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A rejected agreement has to be signed again before the partnership can
 * proceed, and only the partner can do that — so they are told what to fix.
 */
class AgreementRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Agreement $agreement,
        protected string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Action needed: please sign your partnership agreement again')
            ->greeting('Hello')
            ->line('The AHAIC partnerships team reviewed your signed partnership agreement and could not accept it.')
            ->line('Reason: '.$this->reason)
            ->line('Please sign the agreement again — digitally in the portal, or by uploading a new signed PDF.')
            ->action('Sign your agreement', url('/partner/agreement'))
            ->line('If you have questions, reply to this email and the partnerships team will help.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'agreement_id' => $this->agreement->id,
            'reason' => $this->reason,
        ];
    }
}
