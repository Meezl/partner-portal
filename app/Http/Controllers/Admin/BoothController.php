<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booth;
use App\Models\Conference;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BoothController extends Controller
{
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        $booths = $conference
            ? Booth::with('partner:id,organization_name,slug')
                ->where('conference_id', $conference->id)
                ->orderBy('zone')
                ->orderByRaw('CAST(booth_number AS UNSIGNED) ASC')
                ->get()
            : collect();

        $partners = $conference
            ? Partner::where('conference_id', $conference->id)
                ->orderBy('organization_name')
                ->get(['id', 'organization_name', 'slug'])
            : collect();

        $summary = [
            'total' => $booths->count(),
            'available' => $booths->where('status', 'available')->count(),
            'reserved' => $booths->where('status', 'reserved')->count(),
            'assigned' => $booths->where('status', 'assigned')->count(),
            'blocked' => $booths->where('status', 'blocked')->count(),
        ];

        return Inertia::render('Admin/Booths/Index', [
            'conference' => $conference,
            'boothsByZone' => $booths->groupBy('zone'),
            'partners' => $partners,
            'summary' => $summary,
        ]);
    }

    /**
     * Assign a booth to a partner, or release/block it.
     */
    public function update(Request $request, Booth $booth): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['available', 'reserved', 'assigned', 'blocked'])],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $booth) {
            $booth->refresh();

            if ($data['status'] === 'assigned') {
                if (empty($data['partner_id'])) {
                    throw ValidationException::withMessages([
                        'partner_id' => 'Pick a partner to assign this booth to.',
                    ]);
                }

                $conflict = Booth::where('conference_id', $booth->conference_id)
                    ->where('partner_id', $data['partner_id'])
                    ->where('id', '!=', $booth->id)
                    ->where('status', 'assigned')
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'partner_id' => 'This partner already holds another booth. Release it first if you want to reassign.',
                    ]);
                }

                $booth->update([
                    'status' => 'assigned',
                    'partner_id' => $data['partner_id'],
                    'notes' => $data['notes'] ?? null,
                ]);

                return;
            }

            $booth->update([
                'status' => $data['status'],
                'partner_id' => $data['status'] === 'reserved' ? ($data['partner_id'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return back()->with('success', 'Booth updated.');
    }
}
