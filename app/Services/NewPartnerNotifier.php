<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Agreement;
use App\Models\User;
use App\Notifications\NewPartnerNotification;
use App\Notifications\PartnerWelcomeNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Announces a newly signed partner: the partnerships team get told they have a
 * new partner to look after, and the partner gets thanked for registering.
 *
 * Signing is the seam for both — it is the first moment the partnership is
 * binding, and the partner is already on the portal to hear it.
 */
class NewPartnerNotifier
{
    /**
     * The partnerships team's own user accounts. Finance and the central
     * mailbox are told separately by AgreementSignedNotification, which is
     * about the invoice rather than the relationship.
     */
    private const TEAM_ROLES = [
        UserRole::Partnerships,
    ];

    public function notifySigned(Agreement $agreement): void
    {
        $agreement->loadMissing(['partner.user', 'partner.packages', 'partner.conference']);

        $this->notifyPartnershipsTeam($agreement);
        $this->thankPartner($agreement);
    }

    /**
     * Every active partnerships user, plus any configured partnerships mailbox
     * that no such user already covers.
     */
    private function notifyPartnershipsTeam(Agreement $agreement): void
    {
        $team = $this->teamUsers();

        if ($team->isNotEmpty()) {
            Notification::send($team, new NewPartnerNotification($agreement));
        }

        $covered = $team->pluck('email')
            ->filter()
            ->map(fn (string $email) => mb_strtolower($email))
            ->all();

        foreach ($this->teamMailboxes() as $mailbox) {
            if (in_array(mb_strtolower($mailbox), $covered, true)) {
                continue;
            }

            Notification::route('mail', $mailbox)->notify(new NewPartnerNotification($agreement));
        }
    }

    /**
     * The partner's own account owner. A partner without a user account —
     * one the team registered on their behalf — simply has nobody to thank.
     */
    private function thankPartner(Agreement $agreement): void
    {
        $user = $agreement->partner?->user;

        if (! $user || blank($user->email)) {
            return;
        }

        $user->notify(new PartnerWelcomeNotification($agreement));
    }

    /**
     * @return Collection<int, User>
     */
    private function teamUsers(): Collection
    {
        return User::query()
            ->whereIn('role', array_column(self::TEAM_ROLES, 'value'))
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
        $configured = config('ahaic.partnerships_emails', []);

        if (! is_array($configured) || $configured === []) {
            return [];
        }

        return collect($configured)
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values()
            ->all();
    }
}
