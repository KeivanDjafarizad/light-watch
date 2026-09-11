<?php

namespace App\Providers;

use App\Actions\Commands\Cp3000CommandEncoder;
use App\Actions\Commands\LuminaP2PCommandEncoder;
use App\Actions\Commands\VendorCommandEncoderRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(VendorCommandEncoderRegistry::class, function ($app) {
            return new VendorCommandEncoderRegistry([
                $app->make(LuminaP2PCommandEncoder::class),
                $app->make(Cp3000CommandEncoder::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

    }

    /**
     * Bring up the whole realtime stack with `composer run dev`:
     * Reverb (WebSockets), the dashboard broadcast loop and MQTT
     * ingestion. In production these run under Supervisor instead
     * (restart-on-crash, see NOTES.md).
     */

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
