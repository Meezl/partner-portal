<?php

namespace App\Http\Controllers\Partner;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Notifications\ChangeRequestSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
            ->with(['session:id,title', 'reviewedBy:id,name'])
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
            'requested_value' => ['required', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $partner = $request->user()->partner;

        // Verify session belongs to partner
        $partner->sessions()->where('id', $validated['conference_session_id'])->firstOrFail();

        $changeRequest = ChangeRequest::create([
            'partner_id' => $partner->id,
            'conference_session_id' => $validated['conference_session_id'],
            'requested_by' => $request->user()->id,
            'type' => $validated['type'],
            'requested_value' => ['note' => $validated['requested_value']],
            'reason' => $validated['reason'] ?? null,
            'status' => ChangeRequestStatus::Pending,
        ]);

        // Nothing else announces a new request, so the queue could sit unread.
        $mailboxes = config('ahaic.change_request_emails')
            ?: [config('ahaic.central_email')];

        Notification::route('mail', array_values(array_filter((array) $mailboxes)))
            ->notify(new ChangeRequestSubmittedNotification(
                $changeRequest->load('partner', 'session'),
            ));

        return redirect()->route('partner.change-requests.index')
            ->with('success', 'Your change request has been submitted.');
    }
}
