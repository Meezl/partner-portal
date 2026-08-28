<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the partnerships team a change request is waiting. Without this a
 * request can sit in the queue indefinitely because nothing announces it.
 */
class ChangeRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected ChangeRequest $changeRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->changeRequest;
        $organisation = $request->partner?->organization_name ?? 'A partner';

        $mail = (new MailMessage)
            ->subject('New change request from '.$organisation)
            ->greeting('Hello')
            ->line($organisation.' has raised a '.$request->type->label().' change request.');

        if ($session = $request->session) {
            $mail->line('Session: '.$session->title);
        }

        if ($request->reason) {
            $mail->line('Reason given: '.$request->reason);
        }

        return $mail
            ->action('Review change requests', url('/admin/change-requests'))
            ->line('The partner is waiting on a decision.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'change_request_id' => $this->changeRequest->id,
            'type' => $this->changeRequest->type->value,
        ];
    }
}
