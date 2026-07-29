<?php

namespace App\Http\Middleware;

use App\Enums\PartnerStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->partner) {
            return redirect()
                ->route('partner.eoi.create')
                ->with('info', 'Please submit your Expression of Interest first. The rest of the partner portal will unlock once your profile is created.');
        }

        $confirmedStatuses = [
            PartnerStatus::Confirmed,
            PartnerStatus::Onboarding,
            PartnerStatus::Submitted,
            PartnerStatus::Scheduled,
            PartnerStatus::Finalized,
        ];

        if (! in_array($user->partner->status, $confirmedStatuses)) {
            return redirect()->route('partner.dashboard')
                ->with('error', 'Your payment must be confirmed before accessing onboarding.');
        }

        return $next($request);
    }
}
