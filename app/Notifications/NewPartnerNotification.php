<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Announces a new partner to the partnerships team the moment their agreement
 * is signed — the point the partnership is real and someone has to own the
 * relationship. This is separate from AgreementSignedNotification, which tells
 * the central and finance mailboxes an invoice is now outstanding.
 */
class NewPartnerNotification extends Notification implements ShouldQueue
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
        $organization = $partner?->organization_name ?: 'A new partner';
        $package = $partner?->sponsorship_package;

        $mail = (new MailMessage)
            ->subject('New AHAIC partner: '.$organization)
            ->greeting('Hello')
            ->line($organization.' has signed their partnership agreement and is now a confirmed AHAIC partner.');

        if ($partner?->contact_person) {
            $mail->line('Main contact: '.$partner->contact_person
                .($partner->contact_title ? ' ('.$partner->contact_title.')' : '')
                .($partner->email ? ' — '.$partner->email : ''));
        }

        if ($partner?->phone) {
            $mail->line('Phone: '.$partner->phone);
        }

        if ($package) {
            $mail->line('Package: '.$package->name.' ('.$package->tier?->label().')');
        }

        if ($partner?->physical_address_formatted) {
            $mail->line('Address: '.$partner->physical_address_formatted);
        }

        $mail->line(match ($this->agreement->signed_method) {
            'upload' => 'They uploaded a signed copy, so the signatures still need checking.',
            'digital' => 'They signed digitally in the partner portal.',
            default => 'Signing method not recorded.',
        });

        return $mail->action('Open the partner record', url('/admin/partners'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'agreement_id' => $this->agreement->id,
            'partner_id' => $this->agreement->partner_id,
        ];
    }
}
