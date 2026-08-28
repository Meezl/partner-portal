<?php

use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Admin\BoothController;
use App\Http\Controllers\Admin\ChangeRequestController;
use App\Http\Controllers\Admin\ConferenceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinalizationController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\LiveDashboardController;
use App\Http\Controllers\Admin\PartnerManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SchedulingController;
use App\Http\Controllers\Admin\SessionReviewController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:super_admin,admin,finance,programme,pco,communications,partnerships'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Conference Management
    Route::get('/conferences', [ConferenceController::class, 'index'])->name('conferences.index');
    Route::get('/conferences/create', [ConferenceController::class, 'create'])->name('conferences.create');
    Route::post('/conferences', [ConferenceController::class, 'store'])->name('conferences.store');
    Route::get('/conferences/{conference}', [ConferenceController::class, 'edit'])->name('conferences.edit');
    Route::put('/conferences/{conference}', [ConferenceController::class, 'update'])->name('conferences.update');

    // Sponsorship Packages (nested under conferences)
    Route::get('/conferences/{conference}/packages', [ConferenceController::class, 'packages'])->name('conferences.packages');
    Route::post('/conferences/{conference}/packages', [ConferenceController::class, 'storePackage'])->name('conferences.packages.store');
    Route::put('/conferences/{conference}/packages/{package}', [ConferenceController::class, 'updatePackage'])->name('conferences.packages.update');

    // Session review queue (super admin + admin + partnerships)
    Route::middleware(['role:super_admin,admin,partnerships'])->group(function () {
        Route::get('/sessions', [SessionReviewController::class, 'index'])->name('sessions.index');
        Route::put('/sessions/{session}', [SessionReviewController::class, 'update'])->name('sessions.update');
        Route::post('/sessions/{session}/approve', [SessionReviewController::class, 'approve'])->name('sessions.approve');
        Route::post('/sessions/{session}/reject', [SessionReviewController::class, 'reject'])->name('sessions.reject');
    });

    // User Directory (super admin + admin + partnerships)
    Route::middleware(['role:super_admin,admin,partnerships'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
    });

    // User CRUD (super admin + admin only)
    Route::middleware(['role:super_admin,admin'])->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Partner Management
    Route::get('/partners', [PartnerManagementController::class, 'index'])->name('partners.index');
    Route::get('/partners/{partner}', [PartnerManagementController::class, 'show'])->name('partners.show');
    Route::put('/partners/{partner}/status', [PartnerManagementController::class, 'updateStatus'])->name('partners.status');

    // Finance
    Route::middleware(['role:super_admin,admin,finance'])->group(function () {
        Route::get('/finance/payments', [FinanceController::class, 'index'])->name('finance.payments');
        Route::get('/finance/payments/{payment}/proof', [FinanceController::class, 'downloadProof'])->name('finance.payments.proof');
        Route::put('/finance/payments/{payment}/confirm', [FinanceController::class, 'confirm'])->name('finance.payments.confirm');
        Route::put('/finance/payments/{payment}/reject', [FinanceController::class, 'reject'])->name('finance.payments.reject');
    });

    // Scheduling (Phase 6)
    Route::middleware(['role:super_admin,admin,programme,pco'])->group(function () {
        Route::get('/scheduling', [SchedulingController::class, 'index'])->name('scheduling.index');
        Route::get('/scheduling/sessions', [SchedulingController::class, 'sessions'])->name('scheduling.sessions');
        Route::post('/scheduling/sessions/{session}/assign', [SchedulingController::class, 'assignSession'])->name('scheduling.assign');
        Route::put('/scheduling/sessions/{session}/update-schedule', [SchedulingController::class, 'updateSchedule'])->name('scheduling.update');
        Route::delete('/scheduling/sessions/{session}/schedule', [SchedulingController::class, 'destroySchedule'])->name('scheduling.destroy');
        Route::get('/scheduling/conflicts', [SchedulingController::class, 'conflicts'])->name('scheduling.conflicts');
    });

    // Room Management
    Route::middleware(['role:super_admin,admin,pco'])->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    });

    // Booth allocation
    Route::middleware(['role:super_admin,admin,pco,partnerships'])->group(function () {
        Route::get('/booths', [BoothController::class, 'index'])->name('booths.index');
        Route::put('/booths/{booth}', [BoothController::class, 'update'])->name('booths.update');
    });

    // Resource Assignment
    Route::middleware(['role:super_admin,admin,programme,pco'])->group(function () {
        Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
        Route::post('/resources', [ResourceController::class, 'store'])->name('resources.store');
        Route::post('/resources/auto-assign', [ResourceController::class, 'autoAssign'])->name('resources.auto-assign');
        Route::put('/resources/{resource}', [ResourceController::class, 'update'])->name('resources.update');
        Route::delete('/resources/{resource}', [ResourceController::class, 'destroy'])->name('resources.destroy');
    });

    // Agenda
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::get('/agenda/partner/{partner}', [AgendaController::class, 'partnerAgenda'])->name('agenda.partner');
    Route::post('/agenda/publish', [AgendaController::class, 'publish'])->name('agenda.publish');

    // Change Requests (Phase 7) — time/room decisions belong to the partnerships
    // and programme side of the house, not finance or communications.
    Route::middleware(['role:super_admin,admin,partnerships,programme,pco'])->group(function () {
        Route::get('/change-requests', [ChangeRequestController::class, 'index'])->name('change-requests.index');
        Route::put('/change-requests/{changeRequest}/approve', [ChangeRequestController::class, 'approve'])->name('change-requests.approve');
        Route::put('/change-requests/{changeRequest}/reject', [ChangeRequestController::class, 'reject'])->name('change-requests.reject');
    });

    // Finalization (Phase 8)
    Route::middleware(['role:super_admin,admin'])->group(function () {
        Route::get('/finalization', [FinalizationController::class, 'index'])->name('finalization.index');
        Route::post('/finalization/lock', [FinalizationController::class, 'lock'])->name('finalization.lock');
        Route::post('/finalization/publish', [FinalizationController::class, 'publish'])->name('finalization.publish');
    });

    // Live Dashboard (Phase 9)
    Route::get('/live', [LiveDashboardController::class, 'index'])->name('live.index');

    // Reports (Phase 9)
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
});
