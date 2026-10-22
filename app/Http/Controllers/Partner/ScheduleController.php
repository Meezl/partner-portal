<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\SessionSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $partner = $request->user()->partner;

        if (! $partner) {
            return redirect()
                ->route('partner.eoi.create')
                ->with('info', 'Submit your expression of interest first — your conference schedule will appear here once your sessions have been allocated.');
        }

        $schedules = SessionSchedule::with([
                'session:id,partner_id,title,format,expected_participants',
                'room:id,name,building,floor,capacity',
                'timeSlot:id,date,start_time,end_time,label',
            ])
            ->whereHas('session', fn ($q) => $q->where('partner_id', $partner->id))
            ->get();

        return Inertia::render('Partner/Schedule', [
            'partner' => $partner,
            'schedules' => $schedules,
        ]);
    }

    /**
     * The partner confirms the room and time the team allocated them.
     *
     * Confirming says the allocation works, nothing more — it does not change
     * the booking, and the team moving the session clears it again so the
     * partner is asked about the new time rather than the old one.
     */
    public function confirm(Request $request, SessionSchedule $schedule): RedirectResponse
    {
        $partner = $request->user()->partner;

        if (! $partner || $schedule->session?->partner_id !== $partner->id) {
            abort(403, 'This session does not belong to your organization.');
        }

        if ($schedule->confirmedByPartner()) {
            return back()->with('info', 'You have already confirmed this time.');
        }

        $schedule->update(['confirmed_by_partner_at' => now()]);

        return back()->with('success', 'Thank you — your date and time are confirmed.');
    }
}
