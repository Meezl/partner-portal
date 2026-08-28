<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects scripted sign-ups and sign-ins without putting a puzzle in front of
 * real people.
 *
 * Two independent signals, both invisible to a human filling the form:
 *
 *  1. A honeypot field that is off-screen and skipped by the keyboard. A person
 *     never sees it, so any value in it came from something filling every input
 *     it could find.
 *  2. How long the form was on screen. The field is planted when the page
 *     renders; a submission arriving faster than a human could physically type
 *     was not typed.
 *
 * Failures are reported as an ordinary validation error on the honeypot field,
 * which is not rendered — a bot learns nothing about why it was refused.
 */
class BlockAutomatedSubmissions
{
    /** Name of the off-screen field. Deliberately plausible to a scraper. */
    public const HONEYPOT = 'website_url';

    /** Name of the field carrying the render time. */
    public const TIMESTAMP = 'form_loaded_at';

    /** Nobody completes a real sign-up form in under this many seconds. */
    private const MIN_SECONDS = 2;

    /** New accounts allowed per minute from one address. */
    private const REGISTRATIONS_PER_MINUTE = 3;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        // Fortify rate-limits login and two-factor through config, but exposes
        // no limiter for registration, so a script could create accounts in a
        // loop. Keyed by IP because there is no account to key on yet.
        if ($request->is('register')) {
            $key = 'register:'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, self::REGISTRATIONS_PER_MINUTE)) {
                abort(429, 'Too many registration attempts. Please try again shortly.');
            }

            RateLimiter::hit($key, 60);
        }

        if (filled($request->input(self::HONEYPOT))) {
            $this->reject($request, 'honeypot filled');
        }

        $loadedAt = $request->input(self::TIMESTAMP);

        // Absent timestamp is not treated as a failure: a legitimate client
        // with a cached page or a non-JS flow would have none, and locking
        // those people out is worse than letting a slow bot through.
        if (is_numeric($loadedAt)) {
            $elapsed = now()->timestamp - ((int) $loadedAt / 1000);

            if ($elapsed < self::MIN_SECONDS) {
                $this->reject($request, 'submitted in '.round($elapsed, 2).'s');
            }
        }

        return $next($request);
    }

    private function reject(Request $request, string $signal): never
    {
        Log::warning('Automated submission blocked', [
            'signal' => $signal,
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        throw ValidationException::withMessages([
            self::HONEYPOT => 'Your submission could not be verified. Please reload the page and try again.',
        ]);
    }
}
