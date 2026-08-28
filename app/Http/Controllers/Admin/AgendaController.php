<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\SessionSchedule;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\AgendaPublishedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    /**
     * Generate master agenda view (day-by-day).
     */
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        $timeSlots = TimeSlot::where('conference_id', $conference?->id)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Group schedule by day
        $agenda = [];
        foreach ($timeSlots as $slot) {
            $date = $slot->date->format('Y-m-d');

            $schedules = SessionSchedule::where('time_slot_id', $slot->id)
                ->with(['conferenceSession.partner', 'room'])
                ->get();

            $agenda[$date][] = [
                'timeSlot' => $slot,
                'schedules' => $schedules,
            ];
        }

        return Inertia::render('Admin/Agenda/Master', [
            'conference' => $conference,
            'agenda' => $agenda,
        ]);
    }

    /**
     * Generate partner-specific agenda.
     */
    public function partnerAgenda(Partner $partner): Response
    {
        $partner->load('packages');

        $schedules = SessionSchedule::whereHas('conferenceSession', function ($q) use ($partner) {
            $q->where('partner_id', $partner->id);
        })
            ->with(['conferenceSession', 'room', 'timeSlot'])
            ->get()
            ->sortBy(function ($schedule) {
                return $schedule->timeSlot->date.' '.$schedule->timeSlot->start_time;
            })
            ->values();

        return Inertia::render('Admin/Agenda/Partner', [
            'partner' => $partner,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Mark agenda as published.
     */
    public function publish(): RedirectResponse
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        if (! $conference) {
            return back()->with('error', 'There is no active conference to publish.');
        }

        $conference->update(['agenda_published_at' => now()]);

        // Everyone with a session in the programme needs to know it is live.
        $recipients = User::whereHas('partner', fn ($q) => $q->where('conference_id', $conference->id))
            ->get();

        Notification::send($recipients, new AgendaPublishedNotification($conference));

        return back()->with('success', "Agenda published. {$recipients->count()} partner(s) notified.");
    }
}
