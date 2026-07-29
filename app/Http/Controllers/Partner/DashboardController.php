<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Booth;
use App\Models\ChangeRequest;
use App\Models\ConferenceSession;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the partner dashboard with overview data.
     */
    public function index(Request $request): Response
    {
        $partner = $request->user()->partner;

        if (!$partner) {
            return Inertia::render('Partner/Dashboard', [
                'partner' => null,
                'status' => null,
                'onboardingProgress' => null,
                'recentInvoices' => [],
                'sessions' => [],
                'changeRequests' => [],
            ]);
        }

        $partner->load(['packages', 'agreements']);

        $recentInvoices = Invoice::where('partner_id', $partner->id)
            ->latest()
            ->take(5)
            ->get();

        $sessions = ConferenceSession::where('partner_id', $partner->id)
            ->latest()
            ->take(5)
            ->get();

        $changeRequests = ChangeRequest::where('partner_id', $partner->id)
            ->latest()
            ->take(5)
            ->get();

        $booths = Booth::where('partner_id', $partner->id)
            ->whereIn('status', ['assigned', 'reserved'])
            ->get(['id', 'zone', 'booth_number', 'size', 'status']);

        return Inertia::render('Partner/Dashboard', [
            'partner' => $partner,
            'status' => $partner->status,
            'onboardingProgress' => $partner->onboarding_progress,
            'recentInvoices' => $recentInvoices,
            'sessions' => $sessions,
            'changeRequests' => $changeRequests,
            'booths' => $booths,
        ]);
    }
}
