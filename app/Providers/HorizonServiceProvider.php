<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');

        // Configure Horizon authentication using dedicated method
        $this->authorization();
    }

    /**
     * Configure the Horizon authorization services.
     *
     * This method is called during boot to set up access control.
     * The middleware handles authentication and authorization checks.
     * This callback simply logs access for audit purposes.
     */
    protected function authorization(): void
    {
        Horizon::auth(function ($request) {
            // Middleware already handles authentication/authorization
            // This callback is reached only if user passed middleware checks
            $user = Auth::guard('feature-flags')->user();
            
            if ($user) {
                // Log Horizon access for security audit
                Log::info('Horizon accessed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'is_super_admin' => $user->is_super_admin,
                    'environment' => app()->environment(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now(),
                ]);
                
                return true;
            }

            // Log unauthorized access attempts (should not reach here due to middleware)
            Log::warning('Unauthorized Horizon access attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            return false;
        });
    }
}

