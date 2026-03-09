<?php

namespace App\Providers;

use App\Services\HighLevelService;
use Illuminate\Support\ServiceProvider;

class HighLevelServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(HighLevelService::class, function ($app) {
            return new HighLevelService(
                config('services.highlevel.token'),
                config('services.highlevel.location_id')
            );
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
