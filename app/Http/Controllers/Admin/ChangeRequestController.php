<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChangeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChangeRequestController extends Controller
{
    /**
     * List all pending change requests.
     */
    public function index(): Response
    {
        $changeRequests = ChangeRequest::where('status', ChangeRequestStatus::PENDING)
            ->with(['partner', 'conferenceSession'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/ChangeRequests/Index', [
            'changeRequests' => $changeRequests,
        ]);
    }

    /**
     * Approve a change request.
     */
    public function approve(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $changeRequest->update([
            'status' => ChangeRequestStatus::APPROVED,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        // Attempt to apply the change
        $session = $changeRequest->conferenceSession;
        if ($session && $changeRequest->type) {
            // Apply the requested value based on change type
            // This is a simplified application; specific logic depends on ChangeRequestType
            $field = $changeRequest->type->value ?? $changeRequest->type;
            if (in_array($field, ['title', 'description', 'format', 'target_audience'])) {
                $session->update([$field => $changeRequest->requested_value]);
            }
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'change_request_approved',
            'auditable_type' => ChangeRequest::class,
            'auditable_id' => $changeRequest->id,
            'old_values' => ['status' => ChangeRequestStatus::PENDING],
            'new_values' => ['status' => ChangeRequestStatus::APPROVED],
        ]);

        return back()->with('success', 'Change request approved.');
    }

    /**
     * Reject a change request.
     */
    public function reject(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:1000'],
        ]);

        $changeRequest->update([
            'status' => ChangeRequestStatus::REJECTED,
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Change request rejected.');
    }
}
