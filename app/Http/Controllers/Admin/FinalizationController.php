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
use App\Models\User;
use App\Notifications\AgendaPublishedNotification;
use App\Notifications\SubmissionLockedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
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

        // Lock all confirmed/submitted partners.
        $partners = Partner::where('conference_id', $conference->id)
            ->whereIn('status', [PartnerStatus::Submitted, PartnerStatus::Confirmed])
            ->get();

        Partner::whereIn('id', $partners->pluck('id'))->update([
            'status' => PartnerStatus::Finalized,
            'locked_at' => now(),
        ]);

        // Lock all scheduled sessions. SessionStatus has no "finalized" case —
        // Confirmed is the terminal pre-conference state, and conference_sessions
        // has no locked_at column, so the partner lock is what gates editing.
        $sessions = ConferenceSession::where('conference_id', $conference->id)
            ->where('status', SessionStatus::Scheduled)
            ->update(['status' => SessionStatus::Confirmed]);

        // Their submissions are now frozen, so tell them.
        foreach ($partners as $partner) {
            $partner->user?->notify(new SubmissionLockedNotification($partner));
        }

        return back()->with('success', "Finalized {$partners->count()} partner(s) and {$sessions} session(s). Partners have been notified.");
    }

    /**
     * Publish the finalized agenda.
     */
    public function publish(): RedirectResponse
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        if (! $conference) {
            return back()->with('error', 'No active conference found.');
        }

        $conference->update([
            'agenda_published_at' => now(),
        ]);

        $recipients = User::whereHas('partner', fn ($q) => $q->where('conference_id', $conference->id))->get();

        Notification::send($recipients, new AgendaPublishedNotification($conference));

        return back()->with('success', "The finalized agenda has been published. {$recipients->count()} partner(s) notified.");
    }
}
