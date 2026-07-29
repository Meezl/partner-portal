<?php

namespace App\Services;

use App\Models\Partner;

class OnboardingProgressService
{
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
        $fields = ['logo_path', 'description', 'social_media', 'number_of_participants'];
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

        $totalRequired = ['title', 'description', 'format', 'target_audience', 'expected_participants'];
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

        $fields = ['requirements', 'media_contact_name', 'media_contact_email'];
        $filled = 0;
        foreach ($fields as $field) {
            if (! empty($branding->$field)) {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }

    private function calculateContactsProgress(Partner $partner): int
    {
        $contacts = $partner->contacts;
        if ($contacts->isEmpty()) {
            return 0;
        }

        $hasSessionLead = $contacts->where('role', 'session_lead')->isNotEmpty();
        $hasCommsLead = $contacts->where('role', 'comms_lead')->isNotEmpty();

        $score = 0;
        if ($hasSessionLead) {
            $score += 50;
        }
        if ($hasCommsLead) {
            $score += 50;
        }

        return $score;
    }
}
