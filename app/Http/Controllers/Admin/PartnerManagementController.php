<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Notifications\InterestApprovedNotification;
use App\Notifications\InterestRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerManagementController extends Controller
{
    /**
     * List all partners with filters.
     */
    public function index(Request $request): Response
    {
        $query = Partner::with('packages');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('package')) {
            $query->whereHas('packages', function ($q) use ($request) {
                $q->where('tier', $request->input('package'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('organization_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $partners = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Partners/Index', [
            'partners' => $partners->items(),
            'partnersCount' => $partners->total(),
            'filters' => $request->only(['status', 'package', 'search']),
            'statuses' => PartnerStatus::cases(),
        ]);
    }

    /**
     * Show partner detail with all their data.
     */
    public function show(Partner $partner): Response
    {
        $partner->load([
            'packages',
            'user',
            'sessions',
            'contacts',
            'agreements',
            'invoices.payments',
            'brandingRequirement',
            'changeRequests',
        ]);

        return Inertia::render('Admin/Partners/Show', [
            'partner' => $partner,
        ]);
    }

    /**
     * Update partner status.
     */
    public function updateStatus(Request $request, Partner $partner): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_column(PartnerStatus::cases(), 'value'))],
        ]);

        $oldStatus = $partner->status;
        $newStatus = PartnerStatus::from($validated['status']);

        $partner->update(['status' => $newStatus]);

        if ($partner->user) {
            if ($newStatus === PartnerStatus::PendingAgreement && $oldStatus !== PartnerStatus::PendingAgreement) {
                $partner->user->notify(new InterestApprovedNotification($partner->fresh('packages')));
            }

            if ($newStatus === PartnerStatus::Rejected && $oldStatus !== PartnerStatus::Rejected) {
                $partner->user->notify(new InterestRejectedNotification($partner));
            }
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'partner_status_updated',
            'auditable_type' => Partner::class,
            'auditable_id' => $partner->id,
            'old_values' => ['status' => $oldStatus?->value ?? $oldStatus],
            'new_values' => ['status' => $newStatus->value],
        ]);

        $message = match ($newStatus) {
            PartnerStatus::PendingAgreement => 'Submission approved. The partner has been emailed to confirm package and legal details.',
            PartnerStatus::Rejected => 'Submission rejected. The partner has been notified.',
            default => "Partner status updated to {$newStatus->value}.",
        };

        return back()->with('success', $message);
    }
}
