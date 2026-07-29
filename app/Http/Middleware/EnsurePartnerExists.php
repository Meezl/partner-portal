<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft-redirect users who don't yet have a Partner profile back to the
 * Expression of Interest form, so internal pages never explode on `->partner`.
 */
class EnsurePartnerExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->partner) {
            return redirect()
                ->route('partner.eoi.create')
                ->with('info', 'Please submit your Expression of Interest first. The rest of the partner portal will unlock once your profile is created.');
        }

        return $next($request);
    }
}
