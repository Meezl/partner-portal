<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\FeedbackSurvey;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Show reports page with available report types.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Reports', [
            'reportTypes' => [
                'partners' => 'Partner Summary',
                'financial' => 'Financial Report',
                'sessions' => 'Sessions Report',
                'feedback' => 'Feedback Summary',
            ],
        ]);
    }

    /**
     * Generate a specific report based on type parameter.
     */
    public function generate(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:partners,financial,sessions,feedback'],
        ]);

        $conference = Conference::where('status', 'active')->latest()->first();

        $data = match ($validated['type']) {
            'partners' => $this->generatePartnerReport($conference),
            'financial' => $this->generateFinancialReport($conference),
            'sessions' => $this->generateSessionsReport($conference),
            'feedback' => $this->generateFeedbackReport($conference),
        };

        if ($request->wantsJson()) {
            return response()->json([
                'type' => $validated['type'],
                'data' => $data,
                'generated_at' => now()->toISOString(),
            ]);
        }

        return back()->with('report', $data);
    }

    /**
     * Generate partner summary report.
     */
    private function generatePartnerReport(?Conference $conference): array
    {
        $partners = Partner::where('conference_id', $conference?->id)
            ->with('packages')
            ->get();

        return [
            'total' => $partners->count(),
            'byStatus' => $partners->groupBy('status')->map->count(),
            'byPackage' => $partners
                ->groupBy(fn ($partner) => $partner->packages->first()?->id ?? 'unassigned')
                ->map->count(),
            'partners' => $partners->map(fn ($p) => [
                'id' => $p->id,
                'organization_name' => $p->organization_name,
                'status' => $p->status,
                'package' => $p->packages->first()?->name,
                'submitted_at' => $p->submitted_at,
            ]),
        ];
    }

    /**
     * Generate financial report.
     */
    private function generateFinancialReport(?Conference $conference): array
    {
        $invoices = Invoice::whereHas('partner', function ($q) use ($conference) {
            $q->where('conference_id', $conference?->id);
        })->with('payments')->get();

        $payments = Payment::whereHas('partner', function ($q) use ($conference) {
            $q->where('conference_id', $conference?->id);
        })->get();

        return [
            'totalInvoiced' => $invoices->sum('amount'),
            'totalPaid' => $payments->where('status', 'confirmed')->sum('amount'),
            'totalPending' => $payments->where('status', 'pending')->sum('amount'),
            'invoiceCount' => $invoices->count(),
            'paymentCount' => $payments->count(),
            'byStatus' => $payments->groupBy('status')->map->sum('amount'),
        ];
    }

    /**
     * Generate sessions report.
     */
    private function generateSessionsReport(?Conference $conference): array
    {
        $sessions = ConferenceSession::where('conference_id', $conference?->id)
            ->with(['partner', 'sessionSchedule.room'])
            ->get();

        return [
            'total' => $sessions->count(),
            'byStatus' => $sessions->groupBy('status')->map->count(),
            'byFormat' => $sessions->groupBy('format')->map->count(),
            'scheduled' => $sessions->filter(fn ($s) => $s->sessionSchedule)->count(),
            'unscheduled' => $sessions->filter(fn ($s) => ! $s->sessionSchedule)->count(),
        ];
    }

    /**
     * Generate feedback summary report.
     */
    private function generateFeedbackReport(?Conference $conference): array
    {
        $surveys = FeedbackSurvey::whereHas('partner', function ($q) use ($conference) {
            $q->where('conference_id', $conference?->id);
        })->with('partner')->get();

        return [
            'totalResponses' => $surveys->count(),
            'surveys' => $surveys->map(fn ($s) => [
                'partner' => $s->partner->organization_name,
                'submitted_at' => $s->submitted_at,
                'responses' => $s->responses,
            ]),
        ];
    }
}
