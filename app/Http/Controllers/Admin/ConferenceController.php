<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\SponsorshipPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConferenceController extends Controller
{
    /**
     * List conferences.
     */
    public function index(): Response
    {
        $conferences = Conference::withCount('partners')
            ->latest()
            ->get();

        return Inertia::render('Admin/Conferences/Index', [
            'conferences' => $conferences,
        ]);
    }

    /**
     * Show create conference form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Conferences/Create');
    }

    /**
     * Store a new conference.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
        ]);

        $conference = Conference::create($validated);

        return redirect()->route('admin.conferences.edit', $conference)
            ->with('success', 'Conference created successfully.');
    }

    /**
     * Show edit conference form with packages.
     */
    public function edit(Conference $conference): Response
    {
        $packages = SponsorshipPackage::where('conference_id', $conference->id)
            ->orderBy('price', 'asc')
            ->get();

        return Inertia::render('Admin/Conferences/Edit', [
            'conference' => $conference,
            'packages' => $packages,
        ]);
    }

    /**
     * Update a conference.
     */
    public function update(Request $request, Conference $conference): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
        ]);

        $conference->update($validated);

        return back()->with('success', 'Conference updated successfully.');
    }

    /**
     * Show packages for a conference.
     */
    public function packages(Conference $conference): Response
    {
        $packages = SponsorshipPackage::where('conference_id', $conference->id)
            ->orderBy('price', 'asc')
            ->get();

        return Inertia::render('Admin/Conferences/Packages', [
            'conference' => $conference,
            'packages' => $packages,
        ]);
    }

    /**
     * Create a new package for a conference.
     */
    public function storePackage(Request $request, Conference $conference): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'benefits' => ['nullable', 'array'],
            'max_sessions' => ['nullable', 'integer', 'min:0'],
            'exhibition_space' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['conference_id'] = $conference->id;
        $validated['is_active'] = $validated['is_active'] ?? true;

        SponsorshipPackage::create($validated);

        return back()->with('success', 'Package created successfully.');
    }

    /**
     * Update a package for a conference.
     */
    public function updatePackage(Request $request, Conference $conference, SponsorshipPackage $package): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'benefits' => ['nullable', 'array'],
            'max_sessions' => ['nullable', 'integer', 'min:0'],
            'exhibition_space' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $package->update($validated);

        return back()->with('success', 'Package updated successfully.');
    }
}
