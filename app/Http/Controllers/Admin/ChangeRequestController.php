<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Services\ChangeRequestNotifier;
use App\Services\SessionTimeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChangeRequestController extends Controller
{
    public function __construct(
        private readonly SessionTimeRequestService $timeRequests,
        private readonly ChangeRequestNotifier $notifier,
    ) {}

    /**
     * List change requests awaiting a decision, newest first.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status', ChangeRequestStatus::Pending->value);

        $changeRequests = ChangeRequest::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['partner:id,organization_name', 'session:id,title', 'requestedBy:id,name', 'reviewedBy:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/ChangeRequests/Index', [
            'changeRequests' => $changeRequests,
            'filters' => ['status' => $status],
            'pendingCount' => ChangeRequest::where('status', ChangeRequestStatus::Pending)->count(),
        ]);
    }

    /**
     * Approve a change request and apply it to the session.
     */
    public function approve(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        if ($changeRequest->status !== ChangeRequestStatus::Pending) {
            return back()->with('error', 'This change request has already been reviewed.');
        }

        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($changeRequest->type === ChangeRequestType::Time) {
            // Promotes the held slot to a confirmed claim on the session.
            $this->timeRequests->approve($changeRequest, $request->user(), $validated['resolution_notes'] ?? null);
        } else {
            $changeRequest->update([
                'status' => ChangeRequestStatus::Approved,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'resolution_notes' => $validated['resolution_notes'] ?? null,
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'change_request_approved',
            'auditable_type' => ChangeRequest::class,
            'auditable_id' => $changeRequest->id,
            'old_values' => ['status' => ChangeRequestStatus::Pending->value],
            'new_values' => [
                'status' => ChangeRequestStatus::Approved->value,
                'applied' => $changeRequest->requested_value,
            ],
        ]);

        $this->notifier->notifyDecision($changeRequest->refresh());

        return back()->with('success', 'Change request approved. The partner and the team have been notified.');
    }

    /**
     * Reject a change request, releasing anything it was holding.
     */
    public function reject(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        if ($changeRequest->status !== ChangeRequestStatus::Pending) {
            return back()->with('error', 'This change request has already been reviewed.');
        }

        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:1000'],
        ]);

        if ($changeRequest->type === ChangeRequestType::Time) {
            $this->timeRequests->reject($changeRequest, $request->user(), $validated['resolution_notes']);
        } else {
            $changeRequest->update([
                'status' => ChangeRequestStatus::Rejected,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'resolution_notes' => $validated['resolution_notes'],
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'change_request_rejected',
            'auditable_type' => ChangeRequest::class,
            'auditable_id' => $changeRequest->id,
            'old_values' => ['status' => ChangeRequestStatus::Pending->value],
            'new_values' => ['status' => ChangeRequestStatus::Rejected->value],
        ]);

        $this->notifier->notifyDecision($changeRequest->refresh());

        return back()->with('success', 'Change request rejected. The partner and the team have been notified.');
    }
}
