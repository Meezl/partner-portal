<?php

namespace App\Notifications;

use App\Models\SessionSchedule;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionScheduledNotification extends Notification
{
    public function __construct(
        protected SessionSchedule $schedule,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->schedule->session;
        $timeSlot = $this->schedule->timeSlot;
        $room = $this->schedule->room;

        return (new MailMessage)
            ->subject('Your Session Has Been Scheduled')
            ->greeting('Hello!')
            ->line('Your session has been scheduled for the AHAIC conference.')
            ->line('Session: '.$session->title)
            ->line('Date: '.($timeSlot->date?->format('F j, Y') ?? 'TBD'))
            ->line('Time: '.($timeSlot->start_time ?? 'TBD').' - '.($timeSlot->end_time ?? 'TBD'))
            ->line('Room: '.($room->name ?? 'TBD'))
            ->action('View Schedule', url('/partner/schedule'))
            ->line('If you have any concerns about this schedule, please submit a change request through the portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'session_title' => $this->schedule->session->title,
        ];
    }
}
