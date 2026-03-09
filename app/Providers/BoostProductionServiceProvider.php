<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Boost\BoostServiceProvider as BaseBoostServiceProvider;

/**
 * Custom Boost Service Provider for Production Environments
 *
 * This service provider extends Laravel Boost's default provider to allow
 * Boost functionality to run in production environments. By default, Laravel
 * Boost only runs in 'local' environment or when APP_DEBUG=true.
 *
 * This implementation allows Boost to be controlled via the BOOST_ENABLED
 * environment variable, defaulting to TRUE for production servers.
 *
 * @package App\Providers
 */
class BoostProductionServiceProvider extends BaseBoostServiceProvider
{
    /**
     * Determine if Boost should run in the current environment.
     *
     * This method overrides the default behavior to allow Boost to run
     * in production when explicitly enabled via environment configuration.
     *
     * @return bool
     */
    protected function shouldRun(): bool
    {
        // Check if Boost is disabled via config
        if (!config('boost.enabled', true)) {
            return false;
        }

        // Never run Boost during unit tests
        if (app()->runningUnitTests()) {
            return false;
        }

        // Default to TRUE - enabled by default on production
        // To disable, set BOOST_ENABLED=false in environment
        return (bool) config('services.boost.enabled', true);
    }
}

