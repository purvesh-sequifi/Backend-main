<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Features\CustomSalesFieldsFeature;
use App\Models\CompanyProfile;
use Illuminate\Http\JsonResponse;
use Laravel\Pennant\Feature;

/**
 * FeatureFlagController
 * 
 * Provides API endpoints for the frontend to check feature flag status.
 * The frontend should call this on login to determine which features are enabled.
 * 
 * Note: This is a single-tenant application where CompanyProfile::first() 
 * returns the company for the current deployment.
 */
class FeatureFlagController extends Controller
{
    /**
     * Get the company for the current deployment
     */
    private function getCompany(): ?CompanyProfile
    {
        return CompanyProfile::first();
    }

    /**
     * Get all feature flags for the company
     */
    public function index(): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json([
                'status' => false,
                'message' => 'Company profile not found',
            ], 400);
        }

        $flags = [
            CustomSalesFieldsFeature::NAME => Feature::for($company)
                ->active(CustomSalesFieldsFeature::class),
        ];

        return response()->json([
            'status' => true,
            'data' => $flags,
        ]);
    }

    /**
     * Check a specific feature flag
     */
    public function check(string $feature): JsonResponse
    {
        $company = $this->getCompany();

        if (!$company) {
            return response()->json([
                'status' => false,
                'message' => 'Company profile not found',
            ], 400);
        }

        $featureMap = [
            CustomSalesFieldsFeature::NAME => CustomSalesFieldsFeature::class,
        ];

        if (!isset($featureMap[$feature])) {
            return response()->json([
                'status' => false,
                'message' => 'Unknown feature flag',
            ], 404);
        }

        $enabled = Feature::for($company)->active($featureMap[$feature]);

        return response()->json([
            'status' => true,
            'data' => [
                'feature' => $feature,
                'enabled' => $enabled,
            ],
        ]);
    }
}
