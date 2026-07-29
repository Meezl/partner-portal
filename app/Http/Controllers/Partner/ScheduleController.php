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
}
