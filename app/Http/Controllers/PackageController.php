<?php

namespace App\Http\Controllers;

use App\Models\Conference;
use App\Models\SponsorshipPackage;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    /**
     * List active packages for the active conference.
     */
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        $packages = SponsorshipPackage::where('conference_id', $conference?->id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return Inertia::render('Packages/Index', [
            'conference' => $conference,
            'packages' => $packages,
        ]);
    }

    /**
     * Show package detail.
     */
    public function show(SponsorshipPackage $package): Response
    {
        $package->load('conference');

        return Inertia::render('Packages/Show', [
            'package' => $package,
            'conference' => $package->conference,
        ]);
    }
}
