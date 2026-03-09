<?php

namespace App\Providers;

use App\Services\FieldRoutes\DataTransformationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Monolog\Formatter\LineFormatter;

class FieldRoutesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the DataTransformationService as a singleton
        $this->app->singleton(DataTransformationService::class, function ($app) {
            return new DataTransformationService;
        });

        // Configure custom logging
        $this->configureLogging();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/fieldroutes.php' => config_path('fieldroutes.php'),
        ], 'fieldroutes-config');

        // Set up custom logging channel
        if (config('fieldroutes.logging.enabled')) {
            Log::channel(config('fieldroutes.logging.channel'))->info('FieldRoutes service provider initialized');
        }
    }

    /**
     * Configure custom logging for FieldRoutes
     */
    protected function configureLogging(): void
    {
        // Add custom logging channel configuration
        $this->app->config->set('logging.channels.fieldroutes', [
            'driver' => 'daily',
            'path' => config('fieldroutes.logging.file', storage_path('logs/fieldroutes-sync.log')),
            'level' => config('fieldroutes.logging.level', 'info'),
            'days' => 7,
            'formatter' => LineFormatter::class,
            'formatter_with' => [
                'format' => "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'dateFormat' => 'Y-m-d H:i:s',
            ],
        ]);
    }
}
