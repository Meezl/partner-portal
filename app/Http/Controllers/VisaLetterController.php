<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, unauthenticated visa invitation letters for conference attendees.
 *
 * Nothing is stored: the letter is rendered from what was typed and streamed
 * straight back. Abuse is held off by the route's middleware — a per-IP
 * throttle and the same honeypot/timing guard used on sign-in — and search
 * engines are told not to index either the form or the letters.
 */
class VisaLetterController extends Controller
{
    public function create(Request $request): Response
    {
        $conference = $this->activeConference();

        return Inertia::render('VisaLetter', [
            // toArray() applies the model's Y-m-d date casts; only() would hand
            // back Carbon instances that serialise as Nairobi midnight in UTC,
            // i.e. the previous day.
            'conference' => $conference ? Arr::only($conference->toArray(), ['name', 'start_date', 'end_date', 'venue']) : null,
            // The form is a native POST (the response is a file, which an
            // Inertia visit cannot receive), so it needs the token in the body.
            'csrfToken' => csrf_token(),
            'old' => [
                'customer_name' => $request->old('customer_name'),
                'passport_number' => $request->old('passport_number'),
            ],
        ])->toResponse($request)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request): Response|RedirectResponse
    {
        $conference = $this->activeConference();

        if (! $conference) {
            return back()->with('error', 'Visa letters are not available right now.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:150', "regex:/^[\pL\pM\s.'\-]+$/u"],
            'passport_number' => ['required', 'string', 'regex:/^[A-Za-z0-9]{5,20}$/'],
        ], [
            'customer_name.regex' => 'Enter your name as it appears in your passport.',
            'passport_number.regex' => 'Passport numbers are 5–20 letters and digits, with no spaces.',
        ]);

        $name = Str::squish($validated['customer_name']);

        $pdf = Pdf::loadView('pdf.visa-letter', [
            'name' => $name,
            'passportNumber' => Str::upper($validated['passport_number']),
            'conference' => $conference,
        ]);

        return $pdf->download('AHAIC-Visa-Letter-'.Str::slug($name).'.pdf')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function activeConference(): ?Conference
    {
        return Conference::where('status', 'active')->latest()->first();
    }
}
