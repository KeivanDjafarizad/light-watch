<?php

namespace App\Providers;

use App\Actions\Ingestion\Cp3000Adapter;
use App\Actions\Ingestion\LuminaP2PAdapter;
use App\Actions\Ingestion\VendorAdapterRegistry;
use App\Models\Vendor;
use Illuminate\Support\ServiceProvider;

class IngestionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(VendorAdapterRegistry::class, function ($app) {
            return new VendorAdapterRegistry([
                $app->make(LuminaP2PAdapter::class),
                $app->make(Cp3000Adapter::class)
            ]);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
