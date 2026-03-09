<?php

declare(strict_types=1);

namespace App\Services;

use App\Features\CustomSalesFieldsFeature;
use Laravel\Pennant\Feature;

/**
 * FeatureRegistry
 * 
 * Central registry for all feature flags with categories, descriptions,
 * and usage checker configurations.
 */
class FeatureRegistry
{
    /**
     * Get all feature definitions organized by category
     */
    public static function all(): array
    {
        return [
            'sales' => [
                'label' => 'Sales Features',
                'icon' => 'chart-line',
                'description' => 'Features related to sales processing and calculations',
                'features' => [
                    CustomSalesFieldsFeature::NAME => [
                        'name' => 'Custom Sales Fields',
                        'description' => 'Allow custom fields (like System Size) to be used in commission, override, and upfront calculations',
                        'feature_class' => CustomSalesFieldsFeature::class,
                        'usage_checker' => \App\Services\FeatureUsage\CustomSalesFieldsUsageChecker::class,
                        'can_disable_when_in_use' => false,
                    ],
                ],
            ],
            'payroll' => [
                'label' => 'Payroll Features',
                'icon' => 'credit-card',
                'description' => 'Features related to payroll processing and payments',
                'features' => [
                    // Future payroll features can be added here
                ],
            ],
            'integrations' => [
                'label' => 'Integrations',
                'icon' => 'plug',
                'description' => 'Third-party integrations and external services',
                'features' => [
                    // Future integration features can be added here
                ],
            ],
        ];
    }

    /**
     * Get a specific feature by its key
     */
    public static function getFeature(string $featureKey): ?array
    {
        foreach (self::all() as $category) {
            if (isset($category['features'][$featureKey])) {
                return $category['features'][$featureKey];
            }
        }
        return null;
    }

    /**
     * Get all feature keys as a flat array
     */
    public static function getAllFeatureKeys(): array
    {
        $keys = [];
        foreach (self::all() as $category) {
            $keys = array_merge($keys, array_keys($category['features']));
        }
        return $keys;
    }

    /**
     * Get all categories with feature status for a given scope
     */
    public static function getCategoriesWithStatus(object $scope): array
    {
        $categories = self::all();

        foreach ($categories as $categoryKey => &$category) {
            foreach ($category['features'] as $featureKey => &$feature) {
                $featureClass = $feature['feature_class'];
                $feature['is_enabled'] = Feature::for($scope)->active($featureClass);

                // Check usage if checker exists
                if (!empty($feature['usage_checker'])) {
                    try {
                        $checker = app($feature['usage_checker']);
                        $usageDetails = $checker->getUsageDetails();
                        $feature['is_in_use'] = $checker->isInUse();
                        $feature['usage_count'] = $usageDetails['total_count'] ?? 0;
                    } catch (\Throwable $e) {
                        // Fallback if checker fails
                        $feature['is_in_use'] = false;
                        $feature['usage_count'] = 0;
                    }
                } else {
                    $feature['is_in_use'] = false;
                    $feature['usage_count'] = 0;
                }

                // Determine display state
                $feature['state'] = self::determineState(
                    $feature['is_enabled'],
                    $feature['is_in_use']
                );
            }
        }

        return $categories;
    }

    /**
     * Determine the display state of a feature
     */
    public static function determineState(bool $isEnabled, bool $isInUse): array
    {
        if ($isEnabled && !$isInUse) {
            return [
                'key' => 'enabled',
                'label' => 'Enabled',
                'badge_class' => 'bg-green-100 text-green-800',
                'icon' => 'check-circle',
                'warning' => null,
            ];
        }

        if ($isEnabled && $isInUse) {
            return [
                'key' => 'enabled_in_use',
                'label' => 'Enabled + In Use',
                'badge_class' => 'bg-blue-100 text-blue-800',
                'icon' => 'check-circle',
                'warning' => null,
            ];
        }

        if (!$isEnabled && $isInUse) {
            return [
                'key' => 'disabled_in_use',
                'label' => 'In Use + Disabled',
                'badge_class' => 'bg-orange-100 text-orange-800',
                'icon' => 'exclamation-triangle',
                'warning' => 'Feature is disabled but existing data remains. New usage is blocked.',
            ];
        }

        // !$isEnabled && !$isInUse
        return [
            'key' => 'disabled',
            'label' => 'Disabled',
            'badge_class' => 'bg-gray-100 text-gray-600',
            'icon' => 'x-circle',
            'warning' => null,
        ];
    }

    /**
     * Calculate statistics for the dashboard
     */
    public static function getStats(object $scope): array
    {
        $categories = self::getCategoriesWithStatus($scope);

        $totalFeatures = 0;
        $enabledFeatures = 0;
        $inUseFeatures = 0;
        $disabledInUse = 0;

        foreach ($categories as $category) {
            foreach ($category['features'] as $feature) {
                $totalFeatures++;
                if ($feature['is_enabled']) {
                    $enabledFeatures++;
                }
                if ($feature['is_in_use']) {
                    $inUseFeatures++;
                }
                if (!$feature['is_enabled'] && $feature['is_in_use']) {
                    $disabledInUse++;
                }
            }
        }

        return [
            'total' => $totalFeatures,
            'enabled' => $enabledFeatures,
            'disabled' => $totalFeatures - $enabledFeatures,
            'in_use' => $inUseFeatures,
            'disabled_in_use' => $disabledInUse,
        ];
    }
}
