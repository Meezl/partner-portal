<?php

use App\Enums\UserRole;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\VisaLetterController;
use App\Models\Conference;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    // The landing page prints the conference's own dates and venue, so the
    // record stays the single source rather than the copy being hardcoded.
    $conference = Conference::where('status', 'active')->latest()->first();

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'conference' => $conference ? [
            'name' => $conference->name,
            'year' => $conference->year,
            'start_date' => $conference->start_date?->timezone(config('app.timezone'))->toDateString(),
            'end_date' => $conference->end_date?->timezone(config('app.timezone'))->toDateString(),
            'venue' => $conference->venue,
        ] : null,
    ]);
})->name('home');

// Public package browsing
Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package:slug}', [PackageController::class, 'show'])->name('packages.show');

// Public visa invitation letters. Throttle runs before the bot guard so that
// rejected bot attempts still count against the address.
Route::get('/visa-letter', [VisaLetterController::class, 'create'])->name('visa-letter.create');
Route::post('/visa-letter', [VisaLetterController::class, 'store'])
    ->middleware(['throttle:visa-letter', 'bot.guard'])
    ->name('visa-letter.store');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        if ($user?->role === UserRole::Partner) {
            if (! $user->partner) {
                return redirect()->route('partner.eoi.create');
            }

            return redirect()->route('partner.dashboard');
        }

        if ($user?->role) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('home');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/partner.php';
require __DIR__.'/admin.php';
