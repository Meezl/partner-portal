<?php

namespace App\Http\Controllers\Partner;

use App\Enums\PartnerStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Notifications\PartnerSubmitted;
use App\Notifications\SubmissionLockedNotification;
use App\Services\OnboardingProgressService;
use App\Services\SessionTimeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    /**
     * Gather all partner data for review before submission.
     */
    public function review(Request $request): Response
    {
        $partner = $request->user()->partner;

        $partner->load([
            'packages',
            'sessions',
            'contacts',
            'brandingRequirement',
            'agreements',
        ]);
        $progress = app(OnboardingProgressService::class)->calculate($partner);
        $partner->update(['onboarding_progress' => $progress]);

        return Inertia::render('Partner/Review', [
            'partner' => $partner,
            'progress' => $progress,
        ]);
    }

    /**
     * Submit the partner's complete application.
     */
    public function submit(Request $request): RedirectResponse
    {
        $partner = $request->user()->partner;

        // Validate all required sections are complete
        $progress = app(OnboardingProgressService::class)->calculate(
            $partner->fresh(['sessions', 'contacts', 'brandingRequirement']),
        );
        $requiredSections = ['organization', 'sessions', 'communications', 'contacts'];

        $incompleteSections = [];
        foreach ($requiredSections as $section) {
            if (($progress[$section] ?? 0) < 100) {
                $incompleteSections[] = $section;
            }
        }

        if (! empty($incompleteSections)) {
            return back()->with('error', 'Please complete the following sections before submitting: '.implode(', ', $incompleteSections));
        }

        // Verify partner has at least one session
        if ($partner->sessions()->count() === 0) {
            return back()->with('error', 'You must create at least one session before submitting.');
        }

        // Lock the partner data
        $partner->update([
            'submitted_at' => now(),
            'status' => PartnerStatus::Submitted,
            'locked_at' => now(),
            'onboarding_progress' => $progress,
        ]);

        $partner->sessions()
            ->where('status', SessionStatus::Draft)
            ->update([
                'status' => SessionStatus::Submitted,
                'submitted_at' => now(),
            ]);

        // Settle each session's time: the slot it has been holding becomes
        // confirmed and the scheduling board booking is created from it.
        $timeRequests = app(SessionTimeRequestService::class);

        foreach ($partner->sessions()->get() as $session) {
            $timeRequests->grantPendingOnSubmission($session);
        }

        // Notify internal teams
        Notification::route('mail', config('ahaic.team_emails', [config('ahaic.central_email', 'info@ahaic.org')]))
            ->notify(new PartnerSubmitted($partner));

        $request->user()?->notify(new SubmissionLockedNotification($partner));

        return redirect()->route('partner.dashboard')
            ->with('success', 'Your application has been submitted successfully. Our team will review your submission.');
    }
}
