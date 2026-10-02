<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcomes the partner once their agreement is signed and points them at the
 * onboarding they have to complete next.
 *
 * The wording is the partnerships team's own, so it lives in a markdown view
 * rather than being assembled from MailMessage lines — it carries headings and
 * two bullet lists that `line()` cannot render.
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
        $conference = $partner?->conference;
        $conferenceName = $conference?->name ?: 'AHAIC 2027';

        return (new MailMessage)
            ->subject('Welcome to '.$conferenceName.' | Partner Onboarding & Next Steps')
            ->markdown('mail.partner-welcome', [
                'organization' => $partner?->organization_name ?: 'Partner',
                'conferenceName' => $conferenceName,
                'dates' => $this->dates(),
                'venue' => $conference?->venue ?: 'the Kigali Convention Centre, Kigali, Rwanda',
                'arrivalMonth' => $conference?->start_date?->format('F Y') ?: 'February 2027',
                'portalUrl' => url('/partner/onboarding'),
                'websiteUrl' => 'https://ahaic.org',
                'contactEmail' => config('ahaic.central_email', 'ahaic@amref.org'),
            ]);
    }

    /**
     * "28 February to 3 March 2027", from the conference's own dates.
     */
    private function dates(): string
    {
        $conference = $this->agreement->partner?->conference;
        $start = $conference?->start_date;
        $end = $conference?->end_date;

        if (! $start || ! $end) {
            return '28 February to 3 March 2027';
        }

        return $start->format('j F').' to '.$end->format('j F Y');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'agreement_id' => $this->agreement->id,
            'partner_id' => $this->agreement->partner_id,
        ];
    }
}
