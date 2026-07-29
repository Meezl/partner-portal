<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartnerStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\SessionSchedule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FinalizationController extends Controller
{
    /**
     * Show finalization checklist.
     */
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        $allPartnersSubmitted = ! Partner::where('conference_id', $conference?->id)
            ->whereNotIn('status', [PartnerStatus::SUBMITTED, PartnerStatus::CONFIRMED, PartnerStatus::FINALIZED])
            ->exists();

        $allSessionsScheduled = ! ConferenceSession::where('conference_id', $conference?->id)
            ->whereDoesntHave('sessionSchedule')
            ->where('status', '!=', SessionStatus::DRAFT)
            ->exists();

        $conflictCount = SessionSchedule::selectRaw('room_id, time_slot_id, COUNT(*) as count')
            ->groupBy('room_id', 'time_slot_id')
            ->having('count', '>', 1)
            ->count();

        $unresolvedChangeRequests = ChangeRequest::where('status', 'pending')->count();

        return Inertia::render('Admin/Finalization', [
            'conference' => $conference,
            'checklist' => [
                'allPartnersSubmitted' => $allPartnersSubmitted,
                'allSessionsScheduled' => $allSessionsScheduled,
                'noConflicts' => $conflictCount === 0,
                'noUnresolvedChangeRequests' => $unresolvedChangeRequests === 0,
            ],
        ]);
    }

    /**
     * Lock all partners and sessions.
     */
    public function lock(): RedirectResponse
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        if (! $conference) {
            return back()->with('error', 'No active conference found.');
        }

        // Lock all confirmed/submitted partners
        Partner::where('conference_id', $conference->id)
            ->whereIn('status', [PartnerStatus::SUBMITTED, PartnerStatus::CONFIRMED])
            ->update([
                'status' => PartnerStatus::FINALIZED,
                'locked_at' => now(),
            ]);

        // Lock all scheduled sessions
        ConferenceSession::where('conference_id', $conference->id)
            ->where('status', SessionStatus::SCHEDULED)
            ->update([
                'status' => SessionStatus::FINALIZED,
                'locked_at' => now(),
            ]);

        return back()->with('success', 'All partners and sessions have been locked and finalized.');
    }

    /**
     * Publish the finalized agenda.
     */
    public function publish(): RedirectResponse
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        if ($conference) {
            $conference->update([
                'agenda_published_at' => now(),
            ]);
        }

        return back()->with('success', 'The finalized agenda has been published.');
    }
}
