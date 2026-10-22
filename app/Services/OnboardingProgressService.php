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
            'media_lead' => 'Media Contact',
            'additional' => 'Additional Contact',
        ];
    }

    /**
     * Every role a stored contact may carry, including ones the form no longer
     * offers. Used for labelling and validation so a contact saved as a
     * communications lead still reads correctly and still round-trips.
     *
     * @return array<string, string>
     */
    public static function storedContactRoles(): array
    {
        return [...self::contactRoles(), 'comms_lead' => 'Communications Lead'];
    }

    /**
     * The contact every partner must name before the Contacts section counts
     * as done. The forms say so up front, because partners were adding
     * contacts of any role and wondering why the section stayed short of 100%.
     *
     * @return array<string, string>
     */
    public static function mandatoryContactRoles(): array
    {
        return array_intersect_key(self::contactRoles(), array_flip(['session_lead']));
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

        // The section asks what the partner is planning around AHAIC, and
        // for a paragraph on it. Either answers it. Brand guidelines are asked
        // for too, but not every partner has a file to send, so uploads do not
        // gate the section.
        $plans = collect($branding->ahaic_plans ?? [])->filter(fn ($value) => ! empty($value));

        if ($plans->isNotEmpty() || ! empty($branding->plans_summary)) {
            return 100;
        }

        // Answers given before the section was reworked still count.
        $checklist = collect($branding->comms_checklist ?? [])->filter(fn ($value) => ! empty($value));

        return ! empty($branding->requirements) || $checklist->isNotEmpty() ? 100 : 0;
    }

    private function calculateContactsProgress(Partner $partner): int
    {
        $contacts = $partner->contacts;
        $roles = array_keys(self::mandatoryContactRoles());

        // A mandatory contact with a name and an email is the whole section:
        // once it is there the partner is at 100%.
        $named = collect($roles)->filter(fn (string $role) => $contacts
            ->where('role', $role)
            ->filter(fn ($contact) => filled($contact->name) && filled($contact->email))
            ->isNotEmpty(),
        )->count();

        return (int) round(($named / count($roles)) * 100);
    }
}
