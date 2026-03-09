<?php

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use App\Models\Timezone;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ChangeTimeZone
{
    /**
     * Handle an incoming request.
     *
     * Optimized for Octane: Caches company timezone in Redis for 1 hour
     * to avoid database query on every request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Cache company timezone for 1 hour to avoid database query on every request
        $timezone = Cache::remember('company_timezone', 3600, function () {
            $company = CompanyProfile::first();
            
            if (!$company) {
                return config('app.timezone');
            }
            
            $givenOffset = $company->time_zone;
            $timeZone = Timezone::where('name', $givenOffset)->first();
            
            return $timeZone?->timezone ?? config('app.timezone');
        });

        if ($timezone) {
            date_default_timezone_set($timezone);
            config(['app.timezone' => $timezone]);
        }

        return $next($request);
    }
}
