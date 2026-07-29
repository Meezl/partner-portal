<?php

namespace App\Http\Controllers\Partner;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChangeRequestController extends Controller
{
    /**
     * List partner's change requests.
     */
    public function index(Request $request): Response
    {
        $partner = $request->user()->partner;

        $changeRequests = ChangeRequest::where('partner_id', $partner->id)
            ->with('conferenceSession')
            ->latest()
            ->get();

        return Inertia::render('Partner/ChangeRequests/Index', [
            'changeRequests' => $changeRequests,
        ]);
    }

    /**
     * Show change request creation form.
     */
    public function create(Request $request): Response
    {
        $partner = $request->user()->partner;

        $sessions = $partner->sessions()->get();

        return Inertia::render('Partner/ChangeRequests/Create', [
            'sessions' => $sessions,
            'types' => ChangeRequestType::cases(),
        ]);
    }

    /**
     * Store a new change request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'conference_session_id' => ['required', 'exists:conference_sessions,id'],
            'type' => ['required', Rule::in(array_column(ChangeRequestType::cases(), 'value'))],
            'requested_value' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $partner = $request->user()->partner;

        // Verify session belongs to partner
        $partner->sessions()->where('id', $validated['conference_session_id'])->firstOrFail();

        ChangeRequest::create([
            'partner_id' => $partner->id,
            'conference_session_id' => $validated['conference_session_id'],
            'type' => $validated['type'],
            'requested_value' => $validated['requested_value'],
            'reason' => $validated['reason'] ?? null,
            'status' => ChangeRequestStatus::PENDING,
        ]);

        return redirect()->route('partner.change-requests.index')
            ->with('success', 'Your change request has been submitted.');
    }
}
