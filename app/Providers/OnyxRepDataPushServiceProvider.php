<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\OnyxRepDataPushService;
use Illuminate\Support\ServiceProvider;

/**
 * Onyx Rep Data Push Service Provider
 *
 * Registers the OnyxRepDataPushService in the Laravel service container
 * allowing for dependency injection across the application.
 */
class OnyxRepDataPushServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(OnyxRepDataPushService::class, function ($app) {
            return new OnyxRepDataPushService();
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
