<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CompanyProfile;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureFeatureEnabled Middleware
 * 
 * Checks if a feature flag is enabled for the company.
 * Returns 403 with FEATURE_DISABLED error code if not enabled.
 * 
 * Usage in routes: middleware('feature:custom-sales-fields')
 * 
 * Note: This is a single-tenant application where CompanyProfile::first() 
 * returns the company for the current deployment.
 */
class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $feature The feature flag name
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Single-tenant: Get the company for this deployment
        $company = CompanyProfile::first();

        if (!$company) {
            return response()->json([
                'status' => false,
                'message' => 'Company profile not found',
            ], 400);
        }

        $featureClass = $this->getFeatureClass($feature);

        if (!$featureClass) {
            return response()->json([
                'status' => false,
                'message' => 'Unknown feature flag',
                'error' => [
                    'code' => 'UNKNOWN_FEATURE',
                    'feature' => $feature,
                ],
            ], 400);
        }

        if (!Feature::for($company)->active($featureClass)) {
            return response()->json([
                'status' => false,
                'message' => 'This feature is not enabled for your company. Please contact your administrator.',
                'error' => [
                    'code' => 'FEATURE_DISABLED',
                    'feature' => $feature,
                ],
            ], 403);
        }

        return $next($request);
    }

    /**
     * Map feature name to feature class
     *
     * @param string $feature
     * @return string|null
     */
    private function getFeatureClass(string $feature): ?string
    {
        return match ($feature) {
            \App\Features\CustomSalesFieldsFeature::NAME => \App\Features\CustomSalesFieldsFeature::class,
            default => null,
        };
    }
}
