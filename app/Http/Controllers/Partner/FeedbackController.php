<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\FeedbackSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    /**
     * Show feedback survey form.
     */
    public function create(Request $request): Response
    {
        $partner = $request->user()->partner;

        $existingSurvey = FeedbackSurvey::where('partner_id', $partner->id)->first();

        return Inertia::render('Partner/Feedback', [
            'partner' => $partner,
            'existingSurvey' => $existingSurvey,
        ]);
    }

    /**
     * Store or update the feedback survey.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'responses' => ['required', 'array'],
        ]);

        $partner = $request->user()->partner;

        FeedbackSurvey::updateOrCreate(
            ['partner_id' => $partner->id],
            [
                'responses' => $validated['responses'],
                'submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Thank you for your feedback!');
    }
}
