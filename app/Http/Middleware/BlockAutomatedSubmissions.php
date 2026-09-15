<?php

namespace App\Http\Middleware;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
 *  2. How long the form was on screen. The server stamps the render time into
 *     an encrypted token (shared with every page as `botGuardToken`); a
 *     submission arriving faster than a human could physically type was not
 *     typed. Both ends of the measurement are the server's clock, so a visitor
 *     whose device clock is wrong is timed correctly, and the encryption stops
 *     a script from back-dating the stamp.
 *
 * Failures are reported as an ordinary validation error on the honeypot field,
 * which is not rendered — a bot learns nothing about why it was refused.
 */
class BlockAutomatedSubmissions
{
    /** Name of the off-screen field. Deliberately plausible to a scraper. */
    public const HONEYPOT = 'website_url';

    /** Name of the field carrying the encrypted render-time token. */
    public const TIMESTAMP = 'form_loaded_at';

    /** Nobody completes a real sign-up form in under this many seconds. */
    private const MIN_SECONDS = 2;

    /** New accounts allowed per minute from one address. */
    private const REGISTRATIONS_PER_MINUTE = 3;

    /**
     * An encrypted stamp of when a form was rendered, for the page to send back.
     */
    public static function issueToken(?CarbonInterface $renderedAt = null): string
    {
        return Crypt::encryptString((string) ($renderedAt ?? now())->timestamp);
    }

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

        $renderedAt = $this->renderedAt($request->input(self::TIMESTAMP));

        // An absent or unreadable token is not treated as a failure: a
        // legitimate client with a cached page, a non-JS flow, or a page
        // rendered before a deploy would have none, and locking those people
        // out is worse than letting a slow bot through. Rejecting a garbled
        // token would add nothing anyway, since a bot could just omit it.
        if ($renderedAt !== null) {
            $elapsed = now()->timestamp - $renderedAt;

            if ($elapsed < self::MIN_SECONDS) {
                $this->reject($request, 'submitted in '.$elapsed.'s');
            }
        }

        return $next($request);
    }

    /**
     * The server time the form was rendered, or null without a valid token.
     */
    private function renderedAt(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $timestamp = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($timestamp) ? (int) $timestamp : null;
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
