<?php

namespace App\Notifications;

use App\Models\SessionSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SessionScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $timeChanged  True when this replaces a date and time the
     *                             partner already had — the team moving a
     *                             session, rather than placing it for the
     *                             first time.
     * @param  string|null  $previousPlacement  Where it was before, when known.
     */
    public function __construct(
        protected SessionSchedule $schedule,
        protected bool $timeChanged = false,
        protected ?string $previousPlacement = null,
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

        $mail = (new MailMessage)
            ->subject($this->timeChanged
                ? 'Your requested session time has been changed'
                : 'Your Session Has Been Scheduled')
            ->greeting('Hello!')
            ->line($this->timeChanged
                ? 'The AHAIC programme team have changed the date and time you requested for your session.'
                : 'Your session has been scheduled for the AHAIC conference.')
            ->line('Session: '.$session->title);

        if ($this->timeChanged) {
            if ($this->previousPlacement) {
                $mail->line('Previously: '.$this->previousPlacement);
            }

            $mail->line('Now scheduled for:');
        }

        $mail->line('Date: '.($timeSlot->date?->format('F j, Y') ?? 'TBD'))
            ->line('Time: '.($timeSlot->start_time ?? 'TBD').' - '.($timeSlot->end_time ?? 'TBD'))
            ->line('Room: '.($room->name ?? 'TBD'))
            ->action('View Schedule', url('/partner/schedule'));

        return $mail->line($this->timeChanged
            ? 'If this time does not work for you, please submit a change request through the portal.'
            : 'If you have any concerns about this schedule, please submit a change request through the portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'session_title' => $this->schedule->session->title,
            'time_changed' => $this->timeChanged,
        ];
    }
}
