<?php

use App\Http\Controllers\Partner\AgreementController;
use App\Http\Controllers\Partner\ChangeRequestController;
use App\Http\Controllers\Partner\CommitmentController;
use App\Http\Controllers\Partner\DashboardController;
use App\Http\Controllers\Partner\ExpressionOfInterestController;
use App\Http\Controllers\Partner\FeedbackController;
use App\Http\Controllers\Partner\InvoiceController;
use App\Http\Controllers\Partner\OnboardingController;
use App\Http\Controllers\Partner\PaymentController;
use App\Http\Controllers\Partner\ScheduleController;
use App\Http\Controllers\Partner\SessionController;
use App\Http\Controllers\Partner\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:partner'])->prefix('partner')->name('partner.')->group(function () {
    // Expression of Interest (Phase 0-1)
    Route::get('/expression-of-interest', [ExpressionOfInterestController::class, 'create'])->name('eoi.create');
    Route::post('/expression-of-interest', [ExpressionOfInterestController::class, 'store'])->name('eoi.store');
    Route::post('/expression-of-interest/draft', [ExpressionOfInterestController::class, 'saveDraft'])->name('eoi.draft');
    Route::put('/expression-of-interest', [ExpressionOfInterestController::class, 'update'])->name('eoi.update');
});

Route::middleware(['auth', 'verified', 'role:partner', 'partner.exists'])->prefix('partner')->name('partner.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Commitment & Agreement (Phase 2)
    Route::get('/commitment', [CommitmentController::class, 'edit'])->name('commitment.edit');
    Route::put('/commitment', [CommitmentController::class, 'update'])->name('commitment.update');

    Route::get('/agreement', [AgreementController::class, 'show'])->name('agreement.show');
    Route::get('/agreement/download', [AgreementController::class, 'download'])->name('agreement.download');
    Route::post('/agreement/sign', [AgreementController::class, 'sign'])->name('agreement.sign');
    Route::post('/agreement/upload', [AgreementController::class, 'upload'])->name('agreement.upload');

    // Invoices (Phase 2-3)
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    // Payment (Phase 3)
    Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/payment', [PaymentController::class, 'store'])->name('payment.store');

    // Onboarding (Phase 4) — requires confirmed payment
    Route::middleware(['partner.confirmed', 'partner.unlocked'])->group(function () {
        Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
        Route::get('/onboarding/{section}', [OnboardingController::class, 'edit'])->name('onboarding.edit');
        Route::put('/onboarding/{section}', [OnboardingController::class, 'update'])->name('onboarding.update');

        // Sessions
        Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::get('/sessions/create', [SessionController::class, 'create'])->name('sessions.create');
        Route::post('/sessions', [SessionController::class, 'store'])->name('sessions.store');
        Route::get('/sessions/{session}/edit', [SessionController::class, 'edit'])->name('sessions.edit');
        Route::put('/sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
        Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

        // Final Submission (Phase 5)
        Route::get('/review', [SubmissionController::class, 'review'])->name('submission.review');
        Route::post('/submit', [SubmissionController::class, 'submit'])->name('submission.submit');
    });

    // Schedule View (Phase 7) — read-only, available after scheduling
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');

    // Change Requests (Phase 7)
    Route::get('/change-requests', [ChangeRequestController::class, 'index'])->name('change-requests.index');
    Route::get('/change-requests/create', [ChangeRequestController::class, 'create'])->name('change-requests.create');
    Route::post('/change-requests', [ChangeRequestController::class, 'store'])->name('change-requests.store');

    // Feedback (Phase 9)
    Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});
