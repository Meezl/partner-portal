<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ChangeRequest;
use App\Models\User;
use App\Notifications\ChangeRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Fans a reviewed change request out to the partner who raised it and to the
 * admin / partnerships team who need a record of the decision.
 */
class ChangeRequestNotifier
{
    /**
     * Staff roles that own change-request decisions and should be kept in the loop.
     */
    private const STAFF_ROLES = [
        UserRole::SuperAdmin,
        UserRole::Admin,
        UserRole::Partnerships,
    ];

    /**
     * Send approve/reject notifications for a reviewed request.
     */
    public function notifyDecision(ChangeRequest $changeRequest): void
    {
        $changeRequest->loadMissing(['partner.user', 'requestedBy', 'reviewedBy', 'session']);

        $this->notifyPartner($changeRequest);
        $this->notifyStaff($changeRequest);
    }

    /**
     * The partner user who raised the request, plus the partner's account owner.
     */
    private function notifyPartner(ChangeRequest $changeRequest): void
    {
        $recipients = collect([
            $changeRequest->requestedBy,
            $changeRequest->partner?->user,
        ])
            ->filter()
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new ChangeRequestNotification($changeRequest, ChangeRequestNotification::AUDIENCE_PARTNER),
        );
    }

    /**
     * Every active admin/partnerships user, plus any configured team mailbox
     * that no such user already covers.
     */
    private function notifyStaff(ChangeRequest $changeRequest): void
    {
        $notification = fn () => new ChangeRequestNotification(
            $changeRequest,
            ChangeRequestNotification::AUDIENCE_STAFF,
        );

        $staff = $this->staffUsers();

        if ($staff->isNotEmpty()) {
            Notification::send($staff, $notification());
        }

        $covered = $staff->pluck('email')
            ->merge([$changeRequest->requestedBy?->email, $changeRequest->partner?->user?->email])
            ->filter()
            ->map(fn (string $email) => mb_strtolower($email))
            ->all();

        foreach ($this->teamMailboxes() as $mailbox) {
            if (in_array(mb_strtolower($mailbox), $covered, true)) {
                continue;
            }

            Notification::route('mail', $mailbox)->notify($notification());
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function staffUsers(): Collection
    {
        return User::query()
            ->whereIn('role', array_column(self::STAFF_ROLES, 'value'))
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get()
            ->unique('email')
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function teamMailboxes(): array
    {
        $configured = config('ahaic.change_request_emails', []);

        if (! is_array($configured) || $configured === []) {
            $configured = [config('ahaic.central_email')];
        }

        return collect($configured)
            ->filter()
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
