<?php

namespace App\Providers;

use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Observers\PartnerObserver;
use App\Observers\SessionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Partner::observe(PartnerObserver::class);
        ConferenceSession::observe(SessionObserver::class);

        $this->configureRateLimits();
    }

    /**
     * Named limiters for public, unauthenticated endpoints.
     */
    protected function configureRateLimits(): void
    {
        // A person needs one letter, perhaps a few after typos. The daily cap
        // stops a patient script that stays under the per-minute limit. The
        // form is a native POST, so a bare 429 page would strand the visitor;
        // send them back to the form with an explanation instead.
        $backToForm = fn () => back()->withErrors([
            'throttle' => 'Too many letters have been requested from your connection. Please try again later.',
        ]);

        RateLimiter::for('visa-letter', fn (Request $request) => [
            Limit::perMinute(5)->by('visa-letter:minute:'.$request->ip())->response($backToForm),
            Limit::perDay(30)->by('visa-letter:day:'.$request->ip())->response($backToForm),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
