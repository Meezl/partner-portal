<?php

namespace App\Services;

use App\Models\Partner;

class OnboardingProgressService
{
    /**
     * Every role a partner contact can hold, in the order the form offers them.
     *
     * Shared with the forms so the Contacts page and the review summary cannot
     * drift apart on wording.
     *
     * @return array<string, string>
     */
    public static function contactRoles(): array
    {
        return [
            'session_lead' => 'Session Lead',
            'comms_lead' => 'Communications Lead',
            'media_lead' => 'Media Contact',
            'additional' => 'Additional Contact',
        ];
    }

    /**
     * The two contacts every partner must name. Both are required before the
     * Contacts section counts as done — the forms say so up front, because
     * partners were adding two contacts of any role and wondering why the
     * section stayed short of 100%.
     *
     * @return array<string, string>
     */
    public static function mandatoryContactRoles(): array
    {
        return array_intersect_key(self::contactRoles(), array_flip(['session_lead', 'comms_lead']));
    }

    public function calculate(Partner $partner): array
    {
        return [
            'organization' => $this->calculateOrganizationProgress($partner),
            'sessions' => $this->calculateSessionsProgress($partner),
            'communications' => $this->calculateCommunicationsProgress($partner),
            'contacts' => $this->calculateContactsProgress($partner),
        ];
    }

    public function overallProgress(Partner $partner): int
    {
        $progress = $this->calculate($partner);

        return (int) round(array_sum($progress) / count($progress));
    }

    private function calculateOrganizationProgress(Partner $partner): int
    {
        $fields = ['logo_path', 'description', 'social_media'];
        $filled = 0;
        foreach ($fields as $field) {
            if (! empty($partner->$field)) {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }

    private function calculateSessionsProgress(Partner $partner): int
    {
        $sessions = $partner->sessions;
        if ($sessions->isEmpty()) {
            return 0;
        }

        $totalRequired = ['title', 'description', 'format', 'expected_participants'];
        $totalProgress = 0;

        foreach ($sessions as $session) {
            $filled = 0;
            foreach ($totalRequired as $field) {
                if (! empty($session->$field)) {
                    $filled++;
                }
            }
            $totalProgress += ($filled / count($totalRequired)) * 100;
        }

        return (int) round($totalProgress / $sessions->count());
    }

    private function calculateCommunicationsProgress(Partner $partner): int
    {
        $branding = $partner->brandingRequirement;
        if (! $branding) {
            return 0;
        }

        // The section asks one thing: what the partner needs from
        // communications. Either the checklist or the free-text notes answers
        // it. Branding assets are welcome but not every partner has one to
        // send, so they do not gate the section.
        $checklist = collect($branding->comms_checklist ?? [])->filter(fn ($value) => ! empty($value));

        return ! empty($branding->requirements) || $checklist->isNotEmpty() ? 100 : 0;
    }

    private function calculateContactsProgress(Partner $partner): int
    {
        $contacts = $partner->contacts;
        $roles = array_keys(self::mandatoryContactRoles());

        // Both mandatory contacts, each with a name and an email, is the whole
        // section: once they are there the partner is at 100%.
        $named = collect($roles)->filter(fn (string $role) => $contacts
            ->where('role', $role)
            ->filter(fn ($contact) => filled($contact->name) && filled($contact->email))
            ->isNotEmpty(),
        )->count();

        return (int) round(($named / count($roles)) * 100);
    }
}
