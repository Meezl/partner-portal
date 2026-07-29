<?php

use App\Enums\UserRole;
use App\Http\Controllers\PackageController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// Public package browsing
Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package:slug}', [PackageController::class, 'show'])->name('packages.show');

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
