<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\SessionSchedule;
use Inertia\Inertia;
use Inertia\Response;

class LiveDashboardController extends Controller
{
    /**
     * Show the live dashboard with real-time session data.
     */
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        $rooms = Room::with(['sessionSchedules.conferenceSession.partner', 'sessionSchedules.timeSlot'])
            ->get();

        $totalSessions = ConferenceSession::where('conference_id', $conference?->id)->count();
        $scheduledSessions = ConferenceSession::where('conference_id', $conference?->id)
            ->whereHas('sessionSchedule')
            ->count();

        $roomUsage = SessionSchedule::selectRaw('room_id, COUNT(*) as session_count')
            ->groupBy('room_id')
            ->with('room')
            ->get();

        return Inertia::render('Admin/LiveDashboard', [
            'conference' => $conference,
            'rooms' => $rooms,
            'stats' => [
                'totalSessions' => $totalSessions,
                'scheduledSessions' => $scheduledSessions,
                'roomUsage' => $roomUsage,
            ],
        ]);
    }
}
