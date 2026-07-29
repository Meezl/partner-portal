<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPartnerActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $request->user()->partner) {
            AuditLog::create([
                'auditable_type' => 'App\\Models\\Partner',
                'auditable_id' => $request->user()->partner->id,
                'user_id' => $request->user()->id,
                'action' => 'page_visit',
                'new_values' => [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                ],
                'ip_address' => $request->ip(),
            ]);
        }

        return $response;
    }
}
