<?php

namespace App\Notifications;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Models\ChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChangeRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const AUDIENCE_PARTNER = 'partner';

    public const AUDIENCE_STAFF = 'staff';

    public function __construct(
        protected ChangeRequest $changeRequest,
        protected string $audience = self::AUDIENCE_PARTNER,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->audience === self::AUDIENCE_STAFF
            ? $this->staffMail()
            : $this->partnerMail();
    }

    /**
     * Tells the partner what was decided about their request.
     */
    private function partnerMail(): MailMessage
    {
        $approved = $this->changeRequest->status === ChangeRequestStatus::Approved;
        $session = $this->changeRequest->session;
        $isTime = $this->changeRequest->type === ChangeRequestType::Time;

        $message = (new MailMessage)
            ->subject(sprintf(
                '%s %s - %s',
                $isTime ? 'Session time change' : $this->changeRequest->type->label().' change request',
                $approved ? 'approved' : 'not approved',
                $session?->title ?? 'AHAIC session',
            ))
            ->greeting('Hello!')
            ->line($approved
                ? 'Your requested change has been approved by the AHAIC partnerships team.'
                : 'Your requested change could not be approved by the AHAIC partnerships team.')
            ->line('Session: '.($session?->title ?? 'N/A'));

        if ($isTime) {
            $message->line('Requested slot: '.$this->slotLine($this->changeRequest->requested_value));

            if ($previous = $this->slotLine($this->changeRequest->current_value)) {
                $message->line($approved
                    ? 'Previous slot (now released): '.$previous
                    : 'Your session stays at: '.$previous);
            }
        }

        if ($this->changeRequest->resolution_notes) {
            $message->line('Notes from the team: '.$this->changeRequest->resolution_notes);
        }

        return $message
            ->action('View your sessions', url('/partner/sessions'))
            ->line($approved
                ? 'This time is now confirmed on the conference programme.'
                : 'You can choose a different slot from your session page.');
    }

    /**
     * Record copy for the admin and partnerships team, so colleagues can see
     * what was decided and by whom.
     */
    private function staffMail(): MailMessage
    {
        $approved = $this->changeRequest->status === ChangeRequestStatus::Approved;
        $session = $this->changeRequest->session;
        $partner = $this->changeRequest->partner;
        $reviewer = $this->changeRequest->reviewedBy;

        $message = (new MailMessage)
            ->subject(sprintf(
                '[AHAIC] %s change %s - %s',
                $this->changeRequest->type->label(),
                $approved ? 'approved' : 'rejected',
                $partner?->organization_name ?? 'Partner',
            ))
            ->greeting('Hello team,')
            ->line(sprintf(
                'A %s change request was %s by %s.',
                strtolower($this->changeRequest->type->label()),
                $approved ? 'approved' : 'rejected',
                $reviewer?->name ?? 'a team member',
            ))
            ->line('Partner: '.($partner?->organization_name ?? 'N/A'))
            ->line('Session: '.($session?->title ?? 'N/A'));

        if ($this->changeRequest->type === ChangeRequestType::Time) {
            $message->line('Requested slot: '.$this->slotLine($this->changeRequest->requested_value));

            if ($previous = $this->slotLine($this->changeRequest->current_value)) {
                $message->line('Previous slot: '.$previous);
            }
        }

        if ($this->changeRequest->reason) {
            $message->line('Partner\'s reason: '.$this->changeRequest->reason);
        }

        if ($this->changeRequest->resolution_notes) {
            $message->line('Resolution notes: '.$this->changeRequest->resolution_notes);
        }

        return $message->action('Review change requests', url('/admin/change-requests'));
    }

    /**
     * Render a stored slot snapshot as "Parallel 2.1 - Mon, 1 Mar 2027 · 14:00-15:30".
     */
    private function slotLine(?array $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (isset($value['slot_code'], $value['label'])) {
            return $value['slot_code'].' - '.$value['label'];
        }

        return $value['label'] ?? $value['note'] ?? null;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'change_request_id' => $this->changeRequest->id,
            'status' => $this->changeRequest->status->value,
            'type' => $this->changeRequest->type->value,
            'audience' => $this->audience,
        ];
    }
}
