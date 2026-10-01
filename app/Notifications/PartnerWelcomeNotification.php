<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Thanks the partner for registering once their agreement is signed. The
 * invoice notification that follows is a request for money, so this is the
 * one message that simply welcomes them.
 */
class PartnerWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Agreement $agreement) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $partner = $this->agreement->partner;
        $organization = $partner?->organization_name;
        $conference = $partner?->conference;

        $mail = (new MailMessage)
            ->subject('Thank you for partnering with '.($conference?->name ?: 'AHAIC'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Thank you for registering'.($organization ? ' '.$organization : '')
                .' as a partner of '.($conference?->name ?: 'AHAIC').'. Your signed agreement is now on file and we are delighted to have you with us.')
            ->line('Our partnerships team will be in touch, and you can track everything — your sessions, delegates and branding — from your portal dashboard.')
            ->action('Go to your dashboard', url('/partner/dashboard'));

        if ($partner?->physical_address_formatted) {
            $mail->line('We have your address on file as: '.$partner->physical_address_formatted
                .'. Let us know if anything there needs correcting.');
        }

        return $mail->salutation('With thanks,'."\n".'The '.($conference?->name ?: 'AHAIC').' Partnerships Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'agreement_id' => $this->agreement->id,
            'partner_id' => $this->agreement->partner_id,
        ];
    }
}
