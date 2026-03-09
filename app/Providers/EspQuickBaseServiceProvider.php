<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\EspQuickBaseService;
use Illuminate\Support\ServiceProvider;

/**
 * EspQuickBase Service Provider
 * 
 * Registers the EspQuickBaseService in the Laravel service container
 * allowing for dependency injection across the application.
 */
class EspQuickBaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(EspQuickBaseService::class, function ($app) {
            return new EspQuickBaseService();
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

