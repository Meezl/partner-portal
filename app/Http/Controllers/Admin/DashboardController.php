<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SessionSchedule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard with aggregate stats.
     */
    public function index(): Response
    {
        $partnersByStatus = Partner::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $pendingPaymentsCount = Payment::where('status', PaymentStatus::Pending)->count();
        $scheduledSessionsCount = ConferenceSession::whereHas('sessionSchedule')->count();
        $totalRevenue = Payment::where('status', PaymentStatus::Confirmed)->sum('amount');

        $conflictCount = SessionSchedule::selectRaw('room_id, time_slot_id, COUNT(*) as count')
            ->groupBy('room_id', 'time_slot_id')
            ->having('count', '>', 1)
            ->count();

        $recentActivity = AuditLog::with('user')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (AuditLog $entry) => [
                'id' => $entry->id,
                'description' => str($entry->action)->replace('_', ' ')->title()->toString(),
                'causer_name' => $entry->user?->name,
                'created_at' => $entry->created_at?->toISOString(),
                'event' => $entry->action,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'totalPartners' => Partner::count(),
                'pendingPayments' => $pendingPaymentsCount,
                'scheduledSessions' => $scheduledSessionsCount,
                'unresolvedConflicts' => $conflictCount,
                'totalRevenue' => (float) $totalRevenue,
                'partnersByStatus' => $partnersByStatus,
            ],
            'recentActivity' => $recentActivity,
        ]);
    }
}
