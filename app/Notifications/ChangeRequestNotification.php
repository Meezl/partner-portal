<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChangeRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ChangeRequest $changeRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->changeRequest->status->value;
        $session = $this->changeRequest->session;

        return (new MailMessage)
            ->subject('Change Request Update - '.ucfirst($status))
            ->greeting('Hello!')
            ->line('There is an update on a change request for the AHAIC conference.')
            ->line('Session: '.($session->title ?? 'N/A'))
            ->line('Type of Change: '.$this->changeRequest->type->label())
            ->line('Status: '.ucfirst($status))
            ->when($this->changeRequest->resolution_notes, function ($message) {
                return $message->line('Resolution Notes: '.$this->changeRequest->resolution_notes);
            })
            ->action('View Change Request', url('/partner/change-requests'))
            ->line('Please log in to the partner portal for more details.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'change_request_id' => $this->changeRequest->id,
            'status' => $this->changeRequest->status->value,
        ];
    }
}
