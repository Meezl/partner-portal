<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EOISubmittedNotification extends Notification
{
    public function __construct(
        protected Partner $partner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Expression of Interest - '.$this->partner->organization_name)
            ->greeting('Hello!')
            ->line('A new Expression of Interest has been submitted for the AHAIC Partner Portal.')
            ->line('Organization: '.$this->partner->organization_name)
            ->line('Contact Email: '.$this->partner->email)
            ->line('Selected Package: '.($this->partner->packages->first()?->name ?? 'N/A'))
            ->line('Submitted on: '.now()->format('F j, Y'))
            ->line('The partner has automatically moved into package commitment and agreement processing.')
            ->action('View in Portal', url('/admin/partners/'.$this->partner->id))
            ->line('This notification is for visibility and follow-up only.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'partner_id' => $this->partner->id,
            'organization_name' => $this->partner->organization_name,
        ];
    }
}
