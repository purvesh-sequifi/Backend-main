<?php

declare(strict_types=1);

namespace App\Services\FeatureUsage;

use App\Models\Crmcustomfields;
use App\Models\Crmsaleinfo;
use App\Models\OnboardingEmployeeOverride;
use App\Models\OnboardingEmployees;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\User;

/**
 * CustomSalesFieldsUsageChecker
 * 
 * Checks if the Custom Sales Fields feature is currently in use
 * by examining all related database tables.
 */
class CustomSalesFieldsUsageChecker implements FeatureUsageCheckerInterface
{
    /**
     * Check if the feature is currently in use
     */
    public function isInUse(): bool
    {
        $details = $this->getUsageDetails();
        return $details['total_count'] > 0;
    }

    /**
     * Get detailed usage information
     */
    public function getUsageDetails(): array
    {
        $records = $this->getRecordCounts();
        $totalCount = array_sum(array_column($records, 'count'));

        return [
            'modules' => $this->getModules(),
            'records' => $records,
            'total_count' => $totalCount,
            'can_disable' => $totalCount === 0,
            'message' => $this->getMessage($totalCount),
        ];
    }

    /**
     * Get all modules that use this feature
     */
    private function getModules(): array
    {
        return [
            [
                'name' => 'Settings - Custom Sales Fields',
                'description' => 'Custom field definitions and configuration',
                'path' => '/settings/custom-sales-fields',
            ],
            [
                'name' => 'Position Setup',
                'description' => 'Commission, override, and upfront type configuration',
                'path' => '/settings/positions',
            ],
            [
                'name' => 'Hiring Flow',
                'description' => 'Employee commission setup during onboarding',
                'path' => '/hiring',
            ],
            [
                'name' => 'Employment Package',
                'description' => 'User commission/override configuration',
                'path' => '/employees',
            ],
            [
                'name' => 'Sales Calculation',
                'description' => 'Runtime commission calculations using custom field values',
                'path' => '/sales',
            ],
            [
                'name' => 'Reports & Reconciliation',
                'description' => 'Display custom field values in reports',
                'path' => '/reports',
            ],
        ];
    }

    /**
     * Get record counts for each type of usage
     */
    private function getRecordCounts(): array
    {
        return [
            'custom_fields_defined' => [
                'label' => 'Custom Fields Defined',
                'count' => $this->getCustomFieldsCount(),
            ],
            'position_commissions' => [
                'label' => 'Position Commissions Using Custom Fields',
                'count' => $this->getPositionCommissionsCount(),
            ],
            'position_upfronts' => [
                'label' => 'Position Upfronts Using Custom Fields',
                'count' => $this->getPositionUpfrontsCount(),
            ],
            'position_overrides' => [
                'label' => 'Position Overrides Using Custom Fields',
                'count' => $this->getPositionOverridesCount(),
            ],
            'users_with_custom_commission' => [
                'label' => 'Users with Custom Field Commission',
                'count' => $this->getUsersWithCustomCommissionCount(),
            ],
            'onboarding_with_custom_commission' => [
                'label' => 'Onboarding Employees with Custom Field Commission',
                'count' => $this->getOnboardingWithCustomCommissionCount(),
            ],
            'onboarding_overrides_with_custom_field' => [
                'label' => 'Onboarding Overrides Using Custom Fields',
                'count' => $this->getOnboardingOverridesCount(),
            ],
            'sales_with_custom_values' => [
                'label' => 'Sales with Custom Field Values',
                'count' => $this->getSalesWithCustomValuesCount(),
            ],
        ];
    }

    /**
     * Get count of active custom fields
     */
    private function getCustomFieldsCount(): int
    {
        try {
            return Crmcustomfields::where('status', 1)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get count of position commissions using custom fields
     */
    private function getPositionCommissionsCount(): int
    {
        try {
            return PositionCommission::where('commission_amount_type', 'custom field')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get count of position upfronts using custom fields
     */
    private function getPositionUpfrontsCount(): int
    {
        try {
            return PositionCommissionUpfronts::where('calculated_by', 'custom field')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get count of position overrides using custom fields
     */
    private function getPositionOverridesCount(): int
    {
        try {
            return PositionOverride::where('type', 'custom field')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get count of users with custom field commission configured
     */
    private function getUsersWithCustomCommissionCount(): int
    {
        try {
            return User::where(function ($query) {
                $query->whereNotNull('commission_custom_sales_field_id')
                    ->orWhereNotNull('self_gen_commission_custom_sales_field_id')
                    ->orWhereNotNull('upfront_custom_sales_field_id')
                    ->orWhereNotNull('direct_custom_sales_field_id')
                    ->orWhereNotNull('indirect_custom_sales_field_id')
                    ->orWhereNotNull('office_custom_sales_field_id');
            })->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get count of onboarding employees with custom field commission
     */
    private function getOnboardingWithCustomCommissionCount(): int
    {
        try {
            return OnboardingEmployees::where(function ($query) {
                $query->whereNotNull('commission_custom_sales_field_id')
                    ->orWhereNotNull('self_gen_commission_custom_sales_field_id')
                    ->orWhereNotNull('upfront_custom_sales_field_id')
                    ->orWhereNotNull('direct_custom_sales_field_id')
                    ->orWhereNotNull('indirect_custom_sales_field_id')
                    ->orWhereNotNull('office_custom_sales_field_id');
            })->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get count of onboarding employee overrides using custom fields
     */
    private function getOnboardingOverridesCount(): int
    {
        try {
            return OnboardingEmployeeOverride::where(function ($query) {
                $query->whereNotNull('direct_custom_sales_field_id')
                    ->orWhereNotNull('indirect_custom_sales_field_id')
                    ->orWhereNotNull('office_custom_sales_field_id');
            })->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get count of sales with custom field values
     */
    private function getSalesWithCustomValuesCount(): int
    {
        try {
            return Crmsaleinfo::whereNotNull('custom_field_values')
                ->where('custom_field_values', '!=', '[]')
                ->where('custom_field_values', '!=', '{}')
                ->where('custom_field_values', '!=', '')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Generate human-readable message
     */
    private function getMessage(int $totalCount): string
    {
        if ($totalCount === 0) {
            return 'This feature is not in use and can be safely disabled.';
        }

        return "This feature is in use with {$totalCount} records. It cannot be disabled until all usage is removed.";
    }
}
