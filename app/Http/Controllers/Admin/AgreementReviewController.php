<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgreementStatus;
use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\AuditLog;
use App\Notifications\AgreementRejectedNotification;
use App\Notifications\AgreementVerifiedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The partnerships team's check on signed agreements. A partner can sign
 * digitally or upload a wet-signed copy, and either way the portal accepts it
 * at once so the invoice can go out; this is where someone confirms the
 * signature is genuine and complete, or sends it back to be signed again.
 */
class AgreementReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = AgreementStatus::tryFrom((string) $request->query('status')) ?? AgreementStatus::Signed;

        $agreements = Agreement::with(['partner.packages', 'reviewer:id,name'])
            ->where('status', $status)
            ->orderByDesc($status === AgreementStatus::Signed ? 'signed_at' : 'updated_at')
            ->get();

        $counts = Agreement::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Admin/Agreements/Index', [
            'agreements' => $agreements->map(fn (Agreement $agreement) => [
                'id' => $agreement->id,
                'status' => $agreement->status->value,
                'partner' => $agreement->partner?->only(['id', 'organization_name', 'contact_person', 'email']),
                'package' => $agreement->partner?->packages->first()?->name,
                'signed_method' => $agreement->signed_method,
                'signed_by_name' => $agreement->signed_by_name,
                'signed_at' => $agreement->signed_at?->toIso8601String(),
                'has_document' => filled($agreement->signed_document_path ?: $agreement->document_path),
                'reviewer' => $agreement->reviewer?->name,
                'reviewed_at' => $agreement->reviewed_at?->toIso8601String(),
                'review_notes' => $agreement->review_notes,
            ])->values(),
            'status' => $status->value,
            'counts' => collect(AgreementStatus::cases())
                ->mapWithKeys(fn (AgreementStatus $case) => [$case->value => (int) ($counts[$case->value] ?? 0)]),
        ]);
    }

    /**
     * Shown inline so a reviewer can read the signatures in the browser.
     */
    public function document(Agreement $agreement): StreamedResponse
    {
        $path = $agreement->signed_document_path ?: $agreement->document_path;
        $disk = Storage::disk(config('ahaic.disks.private'));

        if (! $path || ! $disk->exists($path)) {
            abort(404, 'Agreement document not found.');
        }

        return $disk->response($path);
    }

    public function verify(Request $request, Agreement $agreement): RedirectResponse
    {
        if ($refusal = $this->notAwaitingReview($agreement)) {
            return $refusal;
        }

        $agreement->update([
            'status' => AgreementStatus::Verified,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => null,
        ]);

        $this->record($request, $agreement, 'agreement_verified', []);
        $agreement->partner?->user?->notify(new AgreementVerifiedNotification($agreement));

        return back()->with('success', 'Agreement verified. The partner has been notified.');
    }

    /**
     * Sends the agreement back to be signed again. The rejected copy stays in
     * storage (its path is kept in the audit log) but is detached, so the
     * partner sees the signing options again rather than their old upload.
     */
    public function reject(Request $request, Agreement $agreement): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Tell the partner what needs fixing — they will see this reason.',
        ]);

        if ($refusal = $this->notAwaitingReview($agreement)) {
            return $refusal;
        }

        $rejected = $agreement->only(['signed_document_path', 'signed_by_name', 'signed_method', 'signed_at']);

        $agreement->update([
            'status' => AgreementStatus::Rejected,
            'signed_document_path' => null,
            'signed_by_name' => null,
            'signed_method' => null,
            'signed_at' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['reason'],
        ]);

        // Point the partner's next step back at the agreement. A partner who
        // has already paid keeps their later status — re-signing must not
        // undo a confirmed payment.
        $partner = $agreement->partner;

        if ($partner?->status === PartnerStatus::PendingPayment) {
            $partner->update(['status' => PartnerStatus::PendingAgreement]);
        }

        $this->record($request, $agreement, 'agreement_rejected', [
            ...$rejected,
            'signed_at' => $rejected['signed_at']?->toIso8601String(),
            'reason' => $validated['reason'],
        ]);
        $partner?->user?->notify(new AgreementRejectedNotification($agreement, $validated['reason']));

        return back()->with('success', 'Agreement sent back. The partner has been asked to sign again.');
    }

    private function notAwaitingReview(Agreement $agreement): ?RedirectResponse
    {
        if ($agreement->status === AgreementStatus::Signed) {
            return null;
        }

        return back()->with('error', 'Only a signed agreement awaiting review can be verified or rejected.');
    }

    private function record(Request $request, Agreement $agreement, string $action, array $details): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => Agreement::class,
            'auditable_id' => $agreement->id,
            'old_values' => ['status' => AgreementStatus::Signed->value, ...$details],
            'new_values' => ['status' => $agreement->status->value],
            'ip_address' => $request->ip(),
        ]);
    }
}
