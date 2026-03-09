<?php

declare(strict_types=1);

namespace App\Core\Traits\SaleTraits;

use App\Models\SalesMaster;
use App\Services\SalesCustomFieldCalculator;

/**
 * CustomFieldConversionTrait
 * 
 * This trait implements the "Trick Subroutine" approach for custom sales fields.
 * It converts commission/upfront/override objects with type "custom field" to "per sale"
 * BEFORE passing them to subroutine methods.
 * 
 * This ensures ZERO modifications to SubroutineTrait, SubroutineOverrideTrait, etc.
 * 
 * FORMULA: [number] × custom_field_value = total
 * Where:
 *   - [number] = The configured commission/override amount
 *   - custom_field_value = The value entered in the custom field on the sale
 *   - total = The calculated per-sale amount
 * 
 * @see /Users/apple/Downloads/Custom_Sales_Fields_Complete_Implementation_Plan.md
 */
trait CustomFieldConversionTrait
{
    /**
     * Prepare commission for subroutine by converting custom field type to per sale
     * 
     * This is the core "trick" - we clone the commission object and modify its type
     * to 'per sale' with the calculated amount, so existing subroutines work unchanged.
     * 
     * Supports multiple property naming conventions:
     * - PositionCommission: commission_amount_type, commission_parentage
     * - UserCommissionHistory: commission_type, commission
     *
     * @param mixed $commission The commission object (PositionCommission, UserCommissionHistory, or similar)
     * @param SalesMaster $sale The sale being processed
     * @return mixed Modified commission with 'per sale' type, or original if not custom field
     */
    protected function prepareCommissionForSubroutine($commission, SalesMaster $sale)
    {
        // Check if this is a custom field commission
        if (!$this->isCustomFieldCommission($commission)) {
            return $commission;
        }

        // Get calculator service
        $calculator = app(SalesCustomFieldCalculator::class);

        // Get custom field value from sale
        $customFieldValue = $calculator->getCustomFieldValue(
            $sale->pid,
            $commission->custom_sales_field_id
        );

        if ($customFieldValue === null) {
            $customFieldValue = 0;
        }

        // Apply formula: [number] × custom_field_value = total
        // Support multiple property names for the configured amount
        $configuredAmount = $commission->commission_parentage 
            ?? $commission->commission 
            ?? 0;
        $calculatedAmount = $configuredAmount * $customFieldValue;

        // Clone commission to avoid modifying original
        $modifiedCommission = clone $commission;

        // TRICK: Change to 'per sale' with calculated amount
        // 
        // NOTE ON DUAL PROPERTY SETTING:
        // Different models use different property names for the same concept:
        // - PositionCommission uses: commission_amount_type, commission_parentage
        // - UserCommissionHistory uses: commission_type, commission
        // 
        // Each model ONLY has one of these properties, so setting both is safe.
        // The property_exists/isset check ensures we only set properties that exist.
        // This allows the trait to work with any commission model without instanceof checks.
        if (property_exists($modifiedCommission, 'commission_amount_type') || isset($modifiedCommission->commission_amount_type)) {
            $modifiedCommission->commission_amount_type = 'per sale';
        }
        if (property_exists($modifiedCommission, 'commission_type') || isset($modifiedCommission->commission_type)) {
            $modifiedCommission->commission_type = 'per sale';
        }
        
        // Update the amount using the appropriate property name
        if (property_exists($modifiedCommission, 'commission_parentage') || isset($modifiedCommission->commission_parentage)) {
            $modifiedCommission->commission_parentage = $calculatedAmount;
        }
        if (property_exists($modifiedCommission, 'commission') || isset($modifiedCommission->commission)) {
            $modifiedCommission->commission = $calculatedAmount;
        }

        // Store metadata in model's attributes array (PHP 8.2 compatible)
        if (method_exists($modifiedCommission, 'setAttribute')) {
            $modifiedCommission->setAttribute('_original_type', 'custom field');
            $modifiedCommission->setAttribute('_custom_field_value', $customFieldValue);
            $modifiedCommission->setAttribute('_custom_field_id', $commission->custom_sales_field_id);
            $modifiedCommission->setAttribute('_configured_amount', $configuredAmount);
        }

        return $modifiedCommission;
    }

    /**
     * Prepare upfront for subroutine by converting custom field type to per sale
     * 
     * Supports multiple property naming conventions:
     * - PositionCommissionUpfronts: upfront_type, upfront_percentage
     * - UserUpfrontHistory: upfront_sale_type, upfront_pay_amount / upfront_amount
     *
     * @param mixed $upfront The upfront object
     * @param SalesMaster $sale The sale being processed
     * @return mixed Modified upfront with 'per sale' type, or original if not custom field
     */
    protected function prepareUpfrontForSubroutine($upfront, SalesMaster $sale)
    {
        // Check if this is a custom field upfront
        if (!$this->isCustomFieldUpfront($upfront)) {
            return $upfront;
        }

        // Get calculator service
        $calculator = app(SalesCustomFieldCalculator::class);

        // Get custom field value from sale
        $customFieldValue = $calculator->getCustomFieldValue(
            $sale->pid,
            $upfront->custom_sales_field_id
        );

        if ($customFieldValue === null) {
            $customFieldValue = 0;
        }

        // Apply formula: [number] × custom_field_value = total
        // Support multiple property names for the configured amount
        $configuredAmount = $upfront->upfront_percentage 
            ?? $upfront->upfront_pay_amount 
            ?? $upfront->upfront_amount 
            ?? 0;
        $calculatedAmount = $configuredAmount * $customFieldValue;

        // Clone upfront to avoid modifying original
        $modifiedUpfront = clone $upfront;

        // TRICK: Change to 'per sale' with calculated amount
        // Update both possible property names to ensure compatibility
        if (property_exists($modifiedUpfront, 'upfront_type') || isset($modifiedUpfront->upfront_type)) {
            $modifiedUpfront->upfront_type = 'per sale';
        }
        if (property_exists($modifiedUpfront, 'upfront_sale_type') || isset($modifiedUpfront->upfront_sale_type)) {
            $modifiedUpfront->upfront_sale_type = 'per sale';
        }
        
        // Update the amount using the appropriate property name
        if (property_exists($modifiedUpfront, 'upfront_percentage') || isset($modifiedUpfront->upfront_percentage)) {
            $modifiedUpfront->upfront_percentage = $calculatedAmount;
        }
        if (property_exists($modifiedUpfront, 'upfront_pay_amount') || isset($modifiedUpfront->upfront_pay_amount)) {
            $modifiedUpfront->upfront_pay_amount = $calculatedAmount;
        }
        if (property_exists($modifiedUpfront, 'upfront_amount') || isset($modifiedUpfront->upfront_amount)) {
            $modifiedUpfront->upfront_amount = $calculatedAmount;
        }

        // Store metadata in model's attributes array (PHP 8.2 compatible)
        if (method_exists($modifiedUpfront, 'setAttribute')) {
            $modifiedUpfront->setAttribute('_original_type', 'custom field');
            $modifiedUpfront->setAttribute('_custom_field_value', $customFieldValue);
            $modifiedUpfront->setAttribute('_custom_field_id', $upfront->custom_sales_field_id);
            $modifiedUpfront->setAttribute('_configured_amount', $configuredAmount);
        }

        return $modifiedUpfront;
    }

    /**
     * Prepare override for subroutine by converting custom field type to per sale
     * 
     * Supports multiple property naming conventions:
     * - PositionOverride: direct_overrides_type, direct_overrides_amount, etc.
     * - UserOverrideHistory: direct_overrides_type, direct_overrides_amount, etc.
     *
     * @param mixed $override The override object
     * @param SalesMaster $sale The sale being processed
     * @return mixed Modified override with 'per sale' type, or original if not custom field
     */
    protected function prepareOverrideForSubroutine($override, SalesMaster $sale)
    {
        // Check if this is a custom field override
        if (!$this->isCustomFieldOverride($override)) {
            return $override;
        }

        // Get calculator service
        $calculator = app(SalesCustomFieldCalculator::class);

        // Clone override to avoid modifying original
        $modifiedOverride = clone $override;
        
        // Process direct overrides
        $directType = $override->direct_overrides_type ?? $override->overrides_type ?? null;
        $directFieldId = $override->direct_custom_sales_field_id ?? $override->custom_sales_field_id ?? null;
        
        if ($directType === 'custom field' && !empty($directFieldId)) {
            $customFieldValue = $calculator->getCustomFieldValue($sale->pid, $directFieldId) ?? 0;
            $configuredAmount = $override->direct_overrides_amount ?? $override->overrides_parentage ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;
            
            // Update type and amount
            if (property_exists($modifiedOverride, 'direct_overrides_type') || isset($modifiedOverride->direct_overrides_type)) {
                $modifiedOverride->direct_overrides_type = 'per sale';
                $modifiedOverride->direct_overrides_amount = $calculatedAmount;
            }
            if (property_exists($modifiedOverride, 'overrides_type') || isset($modifiedOverride->overrides_type)) {
                $modifiedOverride->overrides_type = 'per sale';
            }
            if (property_exists($modifiedOverride, 'overrides_parentage') || isset($modifiedOverride->overrides_parentage)) {
                $modifiedOverride->overrides_parentage = $calculatedAmount;
            }
            
            // Store metadata
            if (method_exists($modifiedOverride, 'setAttribute')) {
                $modifiedOverride->setAttribute('_direct_original_type', 'custom field');
                $modifiedOverride->setAttribute('_direct_custom_field_value', $customFieldValue);
                $modifiedOverride->setAttribute('_direct_custom_field_id', $directFieldId);
                $modifiedOverride->setAttribute('_direct_configured_amount', $configuredAmount);
            }
        }
        
        // Process indirect overrides
        $indirectType = $override->indirect_overrides_type ?? null;
        $indirectFieldId = $override->indirect_custom_sales_field_id ?? null;
        
        if ($indirectType === 'custom field' && !empty($indirectFieldId)) {
            $customFieldValue = $calculator->getCustomFieldValue($sale->pid, $indirectFieldId) ?? 0;
            $configuredAmount = $override->indirect_overrides_amount ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;
            
            if (property_exists($modifiedOverride, 'indirect_overrides_type') || isset($modifiedOverride->indirect_overrides_type)) {
                $modifiedOverride->indirect_overrides_type = 'per sale';
                $modifiedOverride->indirect_overrides_amount = $calculatedAmount;
            }
            
            if (method_exists($modifiedOverride, 'setAttribute')) {
                $modifiedOverride->setAttribute('_indirect_original_type', 'custom field');
                $modifiedOverride->setAttribute('_indirect_custom_field_value', $customFieldValue);
                $modifiedOverride->setAttribute('_indirect_custom_field_id', $indirectFieldId);
                $modifiedOverride->setAttribute('_indirect_configured_amount', $configuredAmount);
            }
        }
        
        // Process office overrides
        $officeType = $override->office_overrides_type ?? null;
        $officeFieldId = $override->office_custom_sales_field_id ?? null;
        
        if ($officeType === 'custom field' && !empty($officeFieldId)) {
            $customFieldValue = $calculator->getCustomFieldValue($sale->pid, $officeFieldId) ?? 0;
            $configuredAmount = $override->office_overrides_amount ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;
            
            if (property_exists($modifiedOverride, 'office_overrides_type') || isset($modifiedOverride->office_overrides_type)) {
                $modifiedOverride->office_overrides_type = 'per sale';
                $modifiedOverride->office_overrides_amount = $calculatedAmount;
            }
            
            if (method_exists($modifiedOverride, 'setAttribute')) {
                $modifiedOverride->setAttribute('_office_original_type', 'custom field');
                $modifiedOverride->setAttribute('_office_custom_field_value', $customFieldValue);
                $modifiedOverride->setAttribute('_office_custom_field_id', $officeFieldId);
                $modifiedOverride->setAttribute('_office_configured_amount', $configuredAmount);
            }
        }

        return $modifiedOverride;
    }

    /**
     * Check if commission uses custom field type
     * 
     * Supports multiple property naming conventions:
     * - PositionCommission: commission_amount_type
     * - UserCommissionHistory: commission_type
     *
     * @param mixed $commission
     * @return bool
     */
    protected function isCustomFieldCommission($commission): bool
    {
        if (!$commission) {
            return false;
        }

        // Check various property names for commission type
        $type = $commission->commission_amount_type 
            ?? $commission->commission_type 
            ?? null;
        
        $fieldId = $commission->custom_sales_field_id ?? null;

        return $type === 'custom field' && !empty($fieldId);
    }

    /**
     * Check if upfront uses custom field type
     * 
     * Supports multiple property naming conventions:
     * - PositionCommissionUpfronts: upfront_type
     * - UserUpfrontHistory: upfront_sale_type
     *
     * @param mixed $upfront
     * @return bool
     */
    protected function isCustomFieldUpfront($upfront): bool
    {
        if (!$upfront) {
            return false;
        }

        // Check various property names for upfront type
        $type = $upfront->upfront_type 
            ?? $upfront->upfront_sale_type 
            ?? null;
        
        $fieldId = $upfront->custom_sales_field_id ?? null;

        return $type === 'custom field' && !empty($fieldId);
    }

    /**
     * Check if override uses custom field type
     * 
     * Supports multiple property naming conventions:
     * - PositionOverride: direct_overrides_type, indirect_overrides_type
     * - UserOverrideHistory: direct_overrides_type, indirect_overrides_type, office_overrides_type
     *
     * @param mixed $override
     * @return bool
     */
    protected function isCustomFieldOverride($override): bool
    {
        if (!$override) {
            return false;
        }

        // Check for direct override custom field
        $directType = $override->direct_overrides_type ?? $override->overrides_type ?? null;
        $directFieldId = $override->direct_custom_sales_field_id ?? $override->custom_sales_field_id ?? null;
        
        if ($directType === 'custom field' && !empty($directFieldId)) {
            return true;
        }
        
        // Check for indirect override custom field
        $indirectType = $override->indirect_overrides_type ?? null;
        $indirectFieldId = $override->indirect_custom_sales_field_id ?? null;
        
        if ($indirectType === 'custom field' && !empty($indirectFieldId)) {
            return true;
        }
        
        // Check for office override custom field
        $officeType = $override->office_overrides_type ?? null;
        $officeFieldId = $override->office_custom_sales_field_id ?? null;
        
        if ($officeType === 'custom field' && !empty($officeFieldId)) {
            return true;
        }

        return false;
    }
}
