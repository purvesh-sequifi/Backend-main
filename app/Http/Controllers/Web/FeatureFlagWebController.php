<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Features\CustomSalesFieldsFeature;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Services\CustomFieldImportSyncService;
use App\Services\FeatureRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Pennant\Feature;

/**
 * FeatureFlagWebController
 * 
 * Handles the Blade-based feature flag dashboard for super admins.
 * This is a secure admin-only interface to enable/disable features.
 * 
 * Features:
 * - Grouped cards UI by category
 * - Usage checking before disable
 * - "In Use + Disabled" state support
 * - Prevent disable for features that can't be disabled when in use
 */
class FeatureFlagWebController extends Controller
{
    /**
     * Display the feature flags dashboard
     */
    public function index(): View
    {
        $company = CompanyProfile::first();
        $categories = FeatureRegistry::getCategoriesWithStatus($company);
        $stats = FeatureRegistry::getStats($company);

        return view('feature-flags.index', [
            'categories' => $categories,
            'stats' => $stats,
            'company' => $company,
        ]);
    }

    /**
     * Get usage details for a specific feature
     */
    public function getUsage(string $feature): JsonResponse
    {
        $featureConfig = FeatureRegistry::getFeature($feature);

        if (!$featureConfig) {
            return response()->json([
                'status' => false,
                'message' => 'Unknown feature',
            ], 404);
        }

        if (empty($featureConfig['usage_checker'])) {
            return response()->json([
                'status' => true,
                'data' => [
                    'modules' => [],
                    'records' => [],
                    'total_count' => 0,
                    'can_disable' => true,
                    'message' => 'No usage tracking available for this feature.',
                ],
                'can_disable_when_in_use' => $featureConfig['can_disable_when_in_use'] ?? true,
            ]);
        }

        try {
            $checker = app($featureConfig['usage_checker']);
            $usageDetails = $checker->getUsageDetails();

            return response()->json([
                'status' => true,
                'data' => $usageDetails,
                'can_disable_when_in_use' => $featureConfig['can_disable_when_in_use'] ?? true,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to check usage: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle a feature flag
     */
    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'feature' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        $company = CompanyProfile::first();
        $featureConfig = FeatureRegistry::getFeature($validated['feature']);

        if (!$featureConfig) {
            return back()->with('error', 'Unknown feature');
        }

        // If trying to DISABLE, check usage
        if (!$validated['enabled']) {
            if (!empty($featureConfig['usage_checker'])) {
                $canDisableWhenInUse = $featureConfig['can_disable_when_in_use'] ?? true;

                if (!$canDisableWhenInUse) {
                    try {
                        $checker = app($featureConfig['usage_checker']);
                        if ($checker->isInUse()) {
                            $usageDetails = $checker->getUsageDetails();
                            return back()->with('error', "Cannot disable: {$usageDetails['message']}");
                        }
                    } catch (\Throwable $e) {
                        return back()->with('error', 'Failed to check usage before disabling.');
                    }
                }
            }
        }

        $featureClass = $featureConfig['feature_class'];

        if ($validated['enabled']) {
            Feature::for($company)->activate($featureClass);
            
            // AUTO-SYNC: When Custom Sales Fields feature is enabled,
            // automatically sync all existing custom fields to the import table
            if ($validated['feature'] === CustomSalesFieldsFeature::NAME) {
                app(CustomFieldImportSyncService::class)->syncAllCustomFields();
            }
        } else {
            Feature::for($company)->deactivate($featureClass);
            
            // AUTO-CLEANUP: When Custom Sales Fields feature is disabled,
            // remove all custom field entries from the import table
            if ($validated['feature'] === CustomSalesFieldsFeature::NAME) {
                app(CustomFieldImportSyncService::class)->syncAllCustomFields();
            }
        }

        // Log activity (optional - requires spatie/laravel-activitylog)
        if (function_exists('activity')) {
            activity()
                ->causedBy(auth('feature-flags')->user())
                ->performedOn($company)
                ->withProperties([
                    'feature' => $validated['feature'],
                    'enabled' => $validated['enabled'],
                ])
                ->log('Feature flag toggled');
        }

        $action = $validated['enabled'] ? 'enabled' : 'disabled';
        return back()->with('success', "Feature '{$featureConfig['name']}' {$action}");
    }
}
