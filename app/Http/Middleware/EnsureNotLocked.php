<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotLocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->partner && $user->partner->locked_at !== null) {
            return redirect()->route('partner.dashboard')
                ->with('error', 'Submissions are locked and can no longer be edited.');
        }

        return $next($request);
    }
}
