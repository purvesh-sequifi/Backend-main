<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\CustomSalesFieldHelper;
use App\Models\Crmcustomfields;
use App\Models\Crmsaleinfo;
use App\Models\CompanyProfile;
use App\Models\SalesMaster;
use App\Models\UserCommissionHistory;
use Illuminate\Support\Facades\Log;

/**
 * SalesCustomFieldCalculator Service
 * 
 * Handles custom sales field value retrieval and commission/override calculations.
 * When a commission, override, or upfront is configured with "custom field" type,
 * this service converts it to "per sale" using the actual field value from the sale.
 * 
 * FORMULA: [number] x custom_field = total
 * Where:
 *   - [number] = The configured commission/override amount (e.g., 50)
 *   - custom_field = The value entered in the custom field on the sale
 *   - total = The calculated per-sale amount
 * 
 * This formula applies to ALL company types (Solar, Pest, Fiber, etc.)
 */
class SalesCustomFieldCalculator
{
    /**
     * Request-scoped cache for custom field definitions.
     * Prevents repeated database queries for the same field within a single request.
     * 
     * @var array<int, Crmcustomfields|null>
     */
    protected array $fieldCache = [];

    /**
     * Get a custom field with request-scoped caching.
     * Eliminates repeated database queries for the same field within a single request.
     *
     * @param int $fieldId
     * @return Crmcustomfields|null
     */
    protected function getCachedField(int $fieldId): ?Crmcustomfields
    {
        if (!array_key_exists($fieldId, $this->fieldCache)) {
            $this->fieldCache[$fieldId] = Crmcustomfields::find($fieldId);
        }
        return $this->fieldCache[$fieldId];
    }

    /**
     * Get the custom field value for a specific sale
     *
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param int $customFieldId The custom field ID
     * @return float|null The field value or null if not found
     */
    public function getCustomFieldValue(int|string $saleId, int $customFieldId): ?float
    {
        // First, check for stored value in crmsaleinfo
        $saleInfo = Crmsaleinfo::where('pid', $saleId)->first();
        $storedValues = [];
        
        if ($saleInfo && $saleInfo->custom_field_values) {
            $storedValues = is_array($saleInfo->custom_field_values)
                ? $saleInfo->custom_field_values
                : json_decode($saleInfo->custom_field_values, true) ?? [];
        }

        // Get the field definition first to check if it's calculated
        $field = $this->getCachedField($customFieldId);
        
        if (!$field) {
            return null;
        }

        // PRIORITY 1: For CALCULATED fields, ALWAYS compute using formula (stored values are auto-calculated and may be stale)
        // Do NOT use stored values for calculated fields - they may be outdated when dependencies change
        if ($field->is_calculated && $field->formula) {
            return $this->calculateFieldValueFromFormula($saleId, $field->formula, $storedValues);
        }

        // PRIORITY 2: For NON-CALCULATED fields, check for manually stored value (user-entered value)
        $storedValue = $storedValues[$customFieldId] ?? $storedValues[(string)$customFieldId] ?? null;
        
        if ($storedValue !== null) {
            return (float) $storedValue;
        }

        // PRIORITY 3: For non-calculated fields with no stored value, return 0
        // If a custom field is not calculated and has no stored value, it means the user hasn't entered a value for this sale
        // In this case, the field should contribute 0 to the calculation (not 1.0, not null)
        return 0.0;
    }

    /**
     * Calculate a field value from its formula
     *
     * @param int|string $saleId The sale PID
     * @param array $formula The formula array from the field's value JSON
     * @param array $storedValues Other stored custom field values
     * @param array $calculationStack Track fields being calculated to prevent infinite recursion
     * @return float|null The calculated value
     */
    protected function calculateFieldValueFromFormula(int|string $saleId, array $formula, array $storedValues, array $calculationStack = []): ?float
    {
        if (!isset($formula['operands']) || !isset($formula['operator'])) {
            return null;
        }

        $operator = $formula['operator'];
        $operands = $formula['operands'];

        // Get the sale data for system field references
        $sale = SalesMaster::where('pid', $saleId)->first();
        
        // Calculate operand values
        $values = [];
        foreach ($operands as $operand) {
            $value = $this->getOperandValue($operand, $sale, $storedValues, $saleId, $calculationStack);
            if ($value === null) {
                $value = 0; // Default to 0 for missing values
            }
            $values[] = $value;
        }

        // Perform calculation based on operator
        return $this->performCalculation($values, $operator);
    }

    /**
     * Get the value of an operand (field reference or constant)
     *
     * @param array $operand The operand definition
     * @param SalesMaster|null $sale The sale record
     * @param array $storedValues Stored custom field values
     * @param int|string $saleId The sale PID
     * @param array $calculationStack Track fields being calculated to prevent infinite recursion
     * @return float|null The operand value
     */
    protected function getOperandValue(array $operand, ?SalesMaster $sale, array $storedValues, int|string $saleId, array $calculationStack = []): ?float
    {
        $type = $operand['type'] ?? null;

        if ($type === 'constant') {
            return (float) ($operand['value'] ?? 0);
        }

        if ($type === 'field') {
            // Get field key - can be 'key' for system fields or 'field_id' for custom field references
            $fieldKey = $operand['key'] ?? null;
            $customFieldId = $operand['field_id'] ?? null;
            
            // If fieldKey looks like custom_field_X, extract the ID
            if ($fieldKey && preg_match('/^custom_field_(\d+)$/', $fieldKey, $matches)) {
                $customFieldId = (int) $matches[1];
                $fieldKey = null; // Not a system field
            }

            // Check if it's a system field reference (by key)
            if ($fieldKey) {
                $systemFields = $this->getSystemSaleFields($sale);
                if (isset($systemFields[$fieldKey])) {
                    return (float) $systemFields[$fieldKey];
                }
                
                // Check if fieldKey is a numeric string (legacy custom field reference)
                if (is_numeric($fieldKey)) {
                    $customFieldId = (int) $fieldKey;
                }
            }

            // Handle custom field reference (by field_id)
            if ($customFieldId) {
                // Check if this field is already being calculated (prevent infinite recursion)
                if (in_array($customFieldId, $calculationStack)) {
                    Log::warning('[CustomSalesFields] Circular reference detected in formula calculation', [
                        'field_id' => $customFieldId,
                        'calculation_stack' => $calculationStack,
                        'sale_id' => $saleId,
                    ]);
                    return 0; // Circular reference detected, return 0
                }

                // PRIORITY 1: For CALCULATED fields, ALWAYS compute using formula
                // Do NOT use stored values for calculated fields - they may be stale
                $referencedField = $this->getCachedField($customFieldId);
                if ($referencedField && $referencedField->is_calculated && $referencedField->formula) {
                    // Add current field to calculation stack to prevent infinite recursion
                    $newStack = array_merge($calculationStack, [$customFieldId]);
                    return $this->calculateFieldValueFromFormula($saleId, $referencedField->formula, $storedValues, $newStack);
                }

                // PRIORITY 2: For NON-CALCULATED fields, check stored values
                $storedValue = $storedValues[$customFieldId] ?? $storedValues[(string)$customFieldId] ?? null;
                if ($storedValue !== null) {
                    return (float) $storedValue;
                }

                // PRIORITY 3: No stored value for non-calculated field, return 0
                return 0.0;
            }
        }

        return null;
    }

    /**
     * Get system field values from a sale
     *
     * @param SalesMaster|null $sale The sale record
     * @return array Associative array of field_key => value
     */
    protected function getSystemSaleFields(?SalesMaster $sale): array
    {
        if (!$sale) {
            return [];
        }

        // Get total_override_amount: first try the column, then calculate from user_overrides
        $totalOverrideAmount = (float) ($sale->total_override_amount ?? 0);
        if ($totalOverrideAmount == 0 && $sale->pid) {
            // Calculate from user_overrides table if column is empty/zero
            $totalOverrideAmount = (float) \App\Models\UserOverrides::where('pid', $sale->pid)
                ->where('is_displayed', '1')
                ->sum('amount');
        }

        return [
            // Core sale fields (All company types)
            'kw' => (float) ($sale->kw ?? 0),
            'system_size' => (float) ($sale->system_size ?? $sale->kw ?? 0),
            'gross_account_value' => (float) ($sale->gross_account_value ?? 0),
            'net_account_value' => (float) ($sale->net_account_value ?? 0),
            'ppw' => (float) ($sale->ppw ?? 0),
            'epc' => (float) ($sale->epc ?? 0),
            'gross_revenue' => (float) ($sale->epc ?? 0), // Mortgage display alias for epc; available for formulas
            'net_epc' => (float) ($sale->net_epc ?? 0),
            'adders' => (float) ($sale->adders ?? 0),
            'show' => (float) ($sale->show ?? $sale->adders ?? 0), // SOW / show; API uses adders when show not present
            'install_cost' => (float) ($sale->install_cost ?? 0),
            'system_cost' => (float) ($sale->system_cost ?? 0),
            'contract_value' => (float) ($sale->contract_value ?? 0),
            'monthly_amount' => (float) ($sale->monthly_amount ?? 0),
            'account_value' => (float) ($sale->account_value ?? 0),
            'total_override_amount' => $totalOverrideAmount,
            'total_commission_amount' => (float) ($sale->total_commission_amount ?? 0),
            
            // Dealer/Finance fields (Mortgage, Solar)
            'dealer_fee_percentage' => (float) ($sale->dealer_fee_percentage ?? 0),
            'dealer_fee_amount' => (float) ($sale->dealer_fee_amount ?? 0),
            'redline' => (float) ($sale->redline ?? 0),
            'cash_amount' => (float) ($sale->cash_amount ?? 0),
            'loan_amount' => (float) ($sale->loan_amount ?? 0),
            'financing_rate' => (float) ($sale->financing_rate ?? 0),
            'financing_term' => (float) ($sale->financing_term ?? 0),
            
            // Milestone amounts
            'm1_amount' => (float) ($sale->m1_amount ?? 0),
            'm2_amount' => (float) ($sale->m2_amount ?? 0),
            
            // Fee and deduction fields
            'cancel_fee' => (float) ($sale->cancel_fee ?? 0),
            'cancel_deduction' => (float) ($sale->cancel_deduction ?? 0),
            'lead_cost_amount' => (float) ($sale->lead_cost_amount ?? 0),
            'adv_pay_back_amount' => (float) ($sale->adv_pay_back_amount ?? 0),
            
            // Payment tracking fields
            'total_amount_for_acct' => (float) ($sale->total_amount_for_acct ?? 0),
            'prev_amount_paid' => (float) ($sale->prev_amount_paid ?? 0),
            'prev_deducted_amount' => (float) ($sale->prev_deducted_amount ?? 0),
            'total_amount_in_period' => (float) ($sale->total_amount_in_period ?? 0),
            
            // Service fields (Pest, Fiber)
            'initial_service_cost' => (float) ($sale->initial_service_cost ?? 0),
            'length_of_agreement' => (float) ($sale->length_of_agreement ?? 0),
            'subscription_payment' => (float) ($sale->subscription_payment ?? 0),
            
            // Commission/Override totals
            'total_commission' => (float) ($sale->total_commission ?? 0),
            'projected_commission' => (float) ($sale->projected_commission ?? 0),
            'total_override' => (float) ($sale->total_override ?? 0),
            'projected_override' => (float) ($sale->projected_override ?? 0),
            
            // Mortgage-specific field aliases
            // gross_revenue maps to epc for mortgage companies
            'gross_revenue' => (float) ($sale->epc ?? 0),
            
            // Show field (numeric value for pest/fiber companies)
            'show' => (float) ($sale->show ?? 0),
        ];
    }

    /**
     * Perform calculation based on operator
     *
     * @param array $values The operand values
     * @param string $operator The operator (add, subtract, multiply, divide)
     * @return float The calculated result
     */
    protected function performCalculation(array $values, string $operator): float
    {
        if (empty($values)) {
            return 0;
        }

        $result = $values[0];
        
        for ($i = 1; $i < count($values); $i++) {
            switch ($operator) {
                case 'add':
                    $result += $values[$i];
                    break;
                case 'subtract':
                    $result -= $values[$i];
                    break;
                case 'multiply':
                    $result *= $values[$i];
                    break;
                case 'divide':
                    if ($values[$i] == 0) {
                        Log::warning('Division by zero in custom field formula calculation', [
                            'result_before_division' => $result,
                            'values' => $values,
                            'operator' => $operator,
                        ]);
                        return 0;
                    }
                    $result /= $values[$i];
                    break;
            }
        }

        return $result;
    }

    /**
     * Convert "custom field" amount type to "per sale" for calculation
     * 
     * FORMULA: [number] x custom_field = total
     * 
     * This is the core "trick" - the backend converts custom field types
     * to per sale with the calculated value, so existing calculation logic
     * doesn't need to be modified.
     * 
     * Example:
     *   - Commission Amount (number) = 50
     *   - Custom Field Value on Sale = 10
     *   - Result = 50 x 10 = 500 (per sale)
     *
     * @param string $amountType The original amount type
     * @param int|null $customFieldId The custom field ID (if applicable)
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param float $originalAmount The configured amount (the [number] in formula)
     * @return array Contains calculation result with metadata
     */
    public function convertToPerSale(
        string $amountType,
        ?int $customFieldId,
        int|string $saleId,
        float $originalAmount
    ): array {
        // If not a custom field type, return as-is
        if ($amountType !== 'custom field' || !$customFieldId) {
            return [
                'amount_type' => $amountType,
                'amount' => $originalAmount,
                'is_custom_field' => false,
            ];
        }

        // Get the actual value from the sale's custom field
        $customFieldValue = $this->getCustomFieldValue($saleId, $customFieldId);

        // If custom field value is null or not set, use 0
        if ($customFieldValue === null) {
            $customFieldValue = 0.0;
        }

        // FORMULA: [number] x custom_field = total
        // Where [number] = originalAmount (configured commission/override amount)
        // And custom_field = value from sale's custom field
        $calculatedAmount = $originalAmount * $customFieldValue;

        return [
            'amount_type' => 'per sale',                      // Convert to per sale for existing logic
            'amount' => $calculatedAmount,                     // The calculated total
            'is_custom_field' => true,                         // Flag for audit/display
            'original_amount' => $originalAmount,              // The configured [number]
            'custom_field_value' => $customFieldValue,         // Value from sale
            'custom_field_id' => $customFieldId,               // Field ID for reference
            'custom_field_name' => $this->getCustomFieldName($customFieldId), // Field name
            'formula' => "{$originalAmount} x {$customFieldValue} = {$calculatedAmount}", // Human-readable
        ];
    }

    /**
     * Convert custom field for commission calculation
     * 
     * SOLAR: [number] x custom_field = total
     * PEST/FIBER: [number] x custom_field = total
     * 
     * Commission is then distributed across milestones per the milestone schema.
     *
     * @param float $commissionAmount The configured commission amount
     * @param string $commissionType The commission type (e.g., 'custom field')
     * @param int|null $customFieldId The custom field ID
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param string|null $companyType Optional company type for future variations
     * @return array Converted commission data
     */
    public function convertCommission(
        float $commissionAmount,
        string $commissionType,
        ?int $customFieldId,
        int|string $saleId,
        ?string $companyType = null
    ): array {
        return $this->convertToPerSale($commissionType, $customFieldId, $saleId, $commissionAmount);
    }

    /**
     * Convert custom field for override calculation
     * 
     * Standard Override:
     *   SOLAR/PEST/FIBER: [number] x custom_field = total
     * 
     * Stack Override:
     *   ((([number] x custom_field)) - (Total Commission + Total Overrides + Lower Stack)) * (Stack / 100)
     *   Note: Stack calculation is handled by existing traits - this just provides the base value
     *
     * @param float $overrideAmount The configured override amount
     * @param string $overrideType The override type (e.g., 'custom field')
     * @param int|null $customFieldId The custom field ID
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param string $overrideCategory 'direct', 'indirect', or 'office'
     * @param string|null $companyType Optional company type for future variations
     * @return array Converted override data
     */
    public function convertOverride(
        float $overrideAmount,
        string $overrideType,
        ?int $customFieldId,
        int|string $saleId,
        string $overrideCategory = 'direct',
        ?string $companyType = null
    ): array {
        $result = $this->convertToPerSale($overrideType, $customFieldId, $saleId, $overrideAmount);
        $result['override_category'] = $overrideCategory;
        return $result;
    }

    /**
     * Convert custom field for upfront calculation
     * 
     * SOLAR/PEST/FIBER: [number] x custom_field = total
     * Upfront follows the same formula as commission.
     *
     * @param float $upfrontAmount The configured upfront amount
     * @param string $upfrontType The upfront type (e.g., 'custom field')
     * @param int|null $customFieldId The custom field ID
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param string|null $companyType Optional company type for future variations
     * @return array Converted upfront data
     */
    public function convertUpfront(
        float $upfrontAmount,
        string $upfrontType,
        ?int $customFieldId,
        int|string $saleId,
        ?string $companyType = null
    ): array {
        return $this->convertToPerSale($upfrontType, $customFieldId, $saleId, $upfrontAmount);
    }

    /**
     * Get the name of a custom field
     *
     * @param int $customFieldId The custom field ID
     * @return string|null The field name or null if not found
     */
    public function getCustomFieldName(int $customFieldId): ?string
    {
        $field = $this->getCachedField($customFieldId);
        return $field?->name;
    }

    /**
     * Get custom field with its value for a specific sale
     *
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param int $customFieldId The custom field ID
     * @return array|null Field details with value or null if not found
     */
    public function getCustomFieldWithValue(int|string $saleId, int $customFieldId): ?array
    {
        $field = $this->getCachedField($customFieldId);
        
        if (!$field) {
            return null;
        }

        $value = $this->getCustomFieldValue($saleId, $customFieldId);

        return [
            'id' => $field->id,
            'name' => $field->name,
            'type' => $field->type,
            'is_calculated' => $field->is_calculated,
            'value' => $value,
        ];
    }

    /**
     * Get all custom field values for a sale
     *
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @return array Associative array of field_id => value
     */
    public function getAllCustomFieldValues(int|string $saleId): array
    {
        $saleInfo = Crmsaleinfo::where('pid', $saleId)->first();

        if (!$saleInfo || !$saleInfo->custom_field_values) {
            return [];
        }

        return is_array($saleInfo->custom_field_values)
            ? $saleInfo->custom_field_values
            : json_decode($saleInfo->custom_field_values, true) ?? [];
    }

    /**
     * Set custom field value for a sale
     *
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param int $customFieldId The custom field ID
     * @param mixed $value The value to set
     * @return bool Success status
     */
    public function setCustomFieldValue(int|string $saleId, int $customFieldId, $value): bool
    {
        $saleInfo = Crmsaleinfo::where('pid', $saleId)->first();

        if (!$saleInfo) {
            return false;
        }

        $values = $this->getAllCustomFieldValues($saleId);
        $values[$customFieldId] = $value;

        $saleInfo->custom_field_values = $values;
        return $saleInfo->save();
    }

    /**
     * Bulk set custom field values for a sale
     *
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @param array $fieldValues Associative array of field_id => value
     * @return bool Success status
     */
    public function setCustomFieldValues(int|string $saleId, array $fieldValues): bool
    {
        $saleInfo = Crmsaleinfo::where('pid', $saleId)->first();

        if (!$saleInfo) {
            return false;
        }

        $existingValues = $this->getAllCustomFieldValues($saleId);
        
        // Use centralized helper for merging (uses + operator to preserve numeric keys)
        $mergedValues = CustomSalesFieldHelper::mergeCustomFieldValues($fieldValues, $existingValues);

        $saleInfo->custom_field_values = $mergedValues;
        return $saleInfo->save();
    }

    /**
     * Check if a custom field is of number type (valid for calculations)
     *
     * @param int $customFieldId The custom field ID
     * @return bool True if field is number type
     */
    public function isNumberTypeField(int $customFieldId): bool
    {
        $field = $this->getCachedField($customFieldId);
        return $field && $field->type === 'number';
    }

    /**
     * Get all active number-type custom fields for a company
     * Only number fields can be used for commission/override/upfront
     *
     * @param int $companyId The company ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getNumberFieldsForCompany(int $companyId)
    {
        return Crmcustomfields::forCompany($companyId)
            ->active()
            ->numberType()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all active number-type custom fields available for position settings
     *
     * @param int $companyId The company ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPositionFieldsForCompany(int $companyId)
    {
        return Crmcustomfields::forCompany($companyId)
            ->active()
            ->numberType()
            ->availableInPosition()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get company type for a sale (for potential future company-specific logic)
     *
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @return string|null The company type (Solar, Pest, Fiber, etc.)
     */
    public function getCompanyTypeForSale(int|string $saleId): ?string
    {
        $saleInfo = Crmsaleinfo::where('pid', $saleId)->first();
        
        if (!$saleInfo) {
            return null;
        }

        $company = CompanyProfile::find($saleInfo->company_id);
        return $company?->company_type;
    }

    /**
     * Calculate display value for reports/UI
     * Returns a formatted string showing the calculation breakdown
     *
     * @param float $originalAmount The configured amount
     * @param int|null $customFieldId The custom field ID
     * @param int|string $saleId The sale PID (can be numeric or alphanumeric)
     * @return array Display data for UI
     */
    public function getDisplayValue(
        float $originalAmount,
        ?int $customFieldId,
        int|string $saleId
    ): array {
        if (!$customFieldId) {
            return [
                'display_type' => 'standard',
                'value' => $originalAmount,
                'formatted' => number_format($originalAmount, 2),
            ];
        }

        $customFieldValue = $this->getCustomFieldValue($saleId, $customFieldId) ?? 0;
        $calculatedAmount = $originalAmount * $customFieldValue;
        $fieldName = $this->getCustomFieldName($customFieldId);

        return [
            'display_type' => 'custom_field',
            'value' => $calculatedAmount,
            'formatted' => number_format($calculatedAmount, 2),
            'breakdown' => [
                'multiplier' => $originalAmount,
                'custom_field_name' => $fieldName,
                'custom_field_value' => $customFieldValue,
                'formula_text' => "{$originalAmount} × {$fieldName} ({$customFieldValue}) = " . number_format($calculatedAmount, 2),
            ],
        ];
    }

    // =========================================================================
    // REDLINE COMMISSION DATA METHODS
    // =========================================================================
    // These methods handle commission data for the redline API response,
    // supporting both standard types (per sale, per kw, percent) and custom fields.

    /**
     * Add commission data to redline response for all reps.
     * 
     * @param array $data Current response data
     * @param string $pid Sale PID
     * @param int|null $closerId Closer 1 ID
     * @param int|null $closer2Id Closer 2 ID
     * @param int|null $setterId Setter 1 ID
     * @param int|null $setter2Id Setter 2 ID
     * @param string|null $approvedDate Approval date
     * @param int|null $productId Product ID
     * @return array Updated data with commission info
     */
    public function addRedlineCommissionData(
        array $data,
        string $pid,
        ?int $closerId,
        ?int $closer2Id,
        ?int $setterId,
        ?int $setter2Id,
        ?string $approvedDate,
        ?int $productId
    ): array {
        // Initialize default values for all reps
        $prefixes = ['closer1', 'closer2', 'setter1', 'setter2'];
        foreach ($prefixes as $prefix) {
            $data[$prefix . '_total_commission'] = null;
            $data[$prefix . '_is_projected'] = null;
            $data[$prefix . '_comp_rate_tooltip'] = null;
        }

        // Get sale data
        $sale = SalesMaster::where('pid', $pid)->first();
        if (!$sale) {
            return $data;
        }

        // Initialize context
        $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();
        $companyProfile = CompanyProfile::first();
        $companyType = $companyProfile->company_type ?? null;

        // Company type flags
        $isPestCompany = $companyProfile && in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE);
        $isTurfCompany = $companyType === CompanyProfile::TURF_COMPANY_TYPE;
        $isSolarCompany = $companyType === CompanyProfile::SOLAR_COMPANY_TYPE;
        $isMortgageCompany = $companyType === CompanyProfile::MORTGAGE_COMPANY_TYPE;
        $isRoofingCompany = $companyType === CompanyProfile::ROOFING_COMPANY_TYPE;

        // Sale values
        $systemSize = (float) ($sale->system_size ?? $sale->kw ?? 0);
        $grossAccountValue = (float) ($sale->gross_account_value ?? 0);
        $netEpc = (float) ($sale->net_epc ?? 0);

        // Company margin (CM)
        $cm = 1.0;
        if ($companyProfile && !empty($companyProfile->company_margin)) {
            $cm = 1 - ((float) $companyProfile->company_margin / 100);
        }

        // Build rep configurations
        $reps = [
            ['id' => $closerId, 'prefix' => 'closer1', 'core_position_id' => 2, 'is_self_gen' => ($setterId == $closerId)],
            ['id' => $closer2Id, 'prefix' => 'closer2', 'core_position_id' => 2, 'is_self_gen' => ($setter2Id == $closer2Id)],
            ['id' => $setterId, 'prefix' => 'setter1', 'core_position_id' => 3, 'is_self_gen' => ($closerId == $setterId)],
            ['id' => $setter2Id, 'prefix' => 'setter2', 'core_position_id' => 3, 'is_self_gen' => ($closer2Id == $setter2Id)],
        ];

        // Pre-fetch all user commissions for this sale to avoid N+1 queries (optimization for mortgage)
        // This reads the ALREADY CALCULATED redline values that were stored during commission processing
        // Note: This is for DISPLAY purposes only - we read the actual redline that was applied, not config
        // Status values: 3 = Approved/Paid, 2 = Approved (these are the committed commission records)
        $userCommissionsCache = [];
        if ($isMortgageCompany) {
            $userIds = array_filter([$closerId, $closer2Id, $setterId, $setter2Id]);
            if (!empty($userIds)) {
                $userCommissions = \App\Models\UserCommission::where('pid', $pid)
                    ->whereIn('user_id', $userIds)
                    ->where('is_last', '1')
                    ->where('is_displayed', '1')
                    ->whereIn('status', ['3', '2'])
                    ->get();
                
                foreach ($userCommissions as $uc) {
                    $userCommissionsCache[$uc->user_id] = $uc;
                }
            }
        }

        // Process each rep
        foreach ($reps as $rep) {
            if (!$rep['id']) {
                continue;
            }

            $userId = $rep['id'];
            $prefix = $rep['prefix'];
            $corePositionId = $rep['core_position_id'];

            // Get user's product for calculations
            $userOrganizationData = checkUsersProductForCalculations($userId, $approvedDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'] ?? null;
            $actualProductId = $userOrganizationData['product']->id ?? $productId;

            // Get commission history
            $query = UserCommissionHistory::where([
                'user_id' => $userId,
                'product_id' => $actualProductId,
            ])->where('commission_effective_date', '<=', $approvedDate);

            if ($rep['is_self_gen'] && @$userOrganizationHistory->self_gen_accounts == 1) {
                $query->whereNull('core_position_id');
            } else {
                $query->where('core_position_id', $corePositionId);
            }

            $commissionHistory = $query->orderBy('commission_effective_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->first();

            if (!$commissionHistory) {
                continue;
            }

            $commissionType = $commissionHistory->commission_type;
            $configuredAmount = (float) ($commissionHistory->commission ?? 0);
            
            // Check if this was originally a custom field (model event may have converted it)
            // The model event sets '_original_type' = 'custom field' when converting
            $wasCustomField = $commissionHistory->getAttribute('_original_type') === 'custom field';
            $originalConfiguredAmount = $commissionHistory->getAttribute('_configured_amount');
            $customFieldId = $commissionHistory->custom_sales_field_id ?? $commissionHistory->getAttribute('_custom_field_id');

            // Handle custom field type (only when feature is enabled)
            // Check either current type OR original type (if model event already converted)
            if ($isCustomFieldsEnabled && ($commissionType === 'custom field' || $wasCustomField) && $customFieldId) {
                // Transform types to custom_field_X format
                $customFieldFormat = 'custom_field_' . $customFieldId;
                $data[$prefix . '_commission_type'] = $customFieldFormat;

                if (isset($data[$prefix . '_redline_type']) && $data[$prefix . '_redline_type'] === 'custom field') {
                    $data[$prefix . '_redline_type'] = $customFieldFormat;
                }

                // IMPORTANT: Custom field commissions are converted to "per sale" by CustomFieldConversionTrait
                // This means SaleProductMaster contains the actual split amounts for custom field sales.
                // We should use SaleProductMaster as the source of truth, not recalculate.
                
                // Get amounts from SaleProductMaster and check if any are projected
                $commission = $this->fetchCommissionFromSaleProductMaster($pid, $prefix, $userId);
                
                // Use != 0 to handle both positive and negative amounts (clawbacks/chargebacks)
                if ($commission['total'] != 0) {
                    $data[$prefix . '_total_commission'] = number_format($commission['total'], 2, '.', '');
                    $data[$prefix . '_is_projected'] = $commission['has_projected'];
                }

                // Build tooltip for custom field
                $customField = $this->getCachedField($customFieldId);
                if ($customField) {
                    // Get the custom field value for this sale
                    $customFieldValue = $this->getCustomFieldValue($pid, $customFieldId);

                    if ($customFieldValue !== null) {
                        // Use original configured amount if model event converted it
                        $multiplier = $originalConfiguredAmount !== null 
                            ? (float) $originalConfiguredAmount 
                            : $configuredAmount;

                        // Build tooltip
                        $formulaExpression = $this->buildFormulaExpression($customField);
                        $data[$prefix . '_comp_rate_tooltip'] = sprintf(
                            '%s x %s',
                            number_format($multiplier, 2),
                            $formulaExpression
                        );
                    }
                }
            } else {
                // Standard commission types (per sale, per kw, percent)
                // Note: Standard commission types do not have tooltips - only custom fields do
                
                // Get redline value for potential future use or other calculations
                // For mortgage companies, use the pre-cached redline from UserCommission
                // Note: We read the ALREADY CALCULATED redline that was stored during commission processing
                $redline = 0;
                if ($isMortgageCompany && $netEpc > 0) {
                    // Use pre-fetched cache to avoid N+1 queries
                    $userCommission = $userCommissionsCache[$userId] ?? null;
                    if ($userCommission) {
                        $redline = (float) ($userCommission->redline ?? 0);
                    }
                } else {
                    $redline = (float) ($data[$prefix . '_redline'] ?? 0);
                }
                
                // Always use actual amounts from SaleProductMaster instead of calculated
                // This ensures:
                // 1. Correct handling of commission splits (multiple reps)
                // 2. Tier-adjusted amounts (already calculated in SaleProductMaster)
                // 3. Actual paid/projected amounts (not theoretical calculations)
                $commission = $this->fetchCommissionFromSaleProductMaster($pid, $prefix, $userId);
                
                // Use != 0 to handle both positive and negative amounts (clawbacks/chargebacks)
                if ($commission['total'] != 0) {
                    $data[$prefix . '_total_commission'] = number_format($commission['total'], 2, '.', '');
                    $data[$prefix . '_is_projected'] = $commission['has_projected'];
                }
                
                // Note: $redline is calculated above but currently not used in this method.
                // It's preserved for:
                // 1. Consistency with the data structure
                // 2. Potential future enhancements
                // 3. Debugging purposes
            }
        }

        return $data;
    }

    /**
     * Fetch commission data from SaleProductMaster for a specific user and prefix.
     * 
     * @param string $pid Sale PID
     * @param string $prefix Rep prefix (closer1, closer2, setter1, setter2)
     * @param int $userId User ID
     * @return array ['total' => float, 'has_projected' => int]
     */
    private function fetchCommissionFromSaleProductMaster(
        string $pid,
        string $prefix,
        int $userId
    ): array {
        $amounts = \App\Models\SaleProductMaster::where('pid', $pid)
            ->where(function ($query) use ($prefix, $userId) {
                // Map prefix to correct column using modern match expression
                match ($prefix) {
                    'closer1' => $query->where('closer1_id', $userId),
                    'closer2' => $query->where('closer2_id', $userId),
                    'setter1' => $query->where('setter1_id', $userId),
                    'setter2' => $query->where('setter2_id', $userId),
                    default => null,
                };
            })
            ->selectRaw('
                SUM(amount) as total,
                MAX(is_projected) as has_projected
            ')
            ->first();

        return [
            'total' => (float) ($amounts->total ?? 0),
            'has_projected' => (int) ($amounts->has_projected ?? 0),
        ];
    }

    /**
     * Calculate commission amount for standard types.
     * 
     * @return array ['commission' => float, 'tooltip' => ?string]
     */
    protected function calculateStandardCommission(
        string $commissionType,
        float $configuredAmount,
        float $redline,
        float $cm,
        float $systemSize,
        float $grossAccountValue,
        float $netEpc,
        bool $isPestCompany,
        bool $isTurfCompany,
        bool $isMortgageCompany,
        bool $isRoofingCompany
    ): array {
        $commission = 0;
        $tooltip = null;

        switch ($commissionType) {
            case 'per sale':
                $commission = $configuredAmount * $cm;
                break;

            case 'per kw':
                $commission = $configuredAmount * $systemSize * $cm;
                break;

            case 'percent':
                if ($isMortgageCompany && $netEpc > 0) {
                    // Mortgage: Fee-based commission calculation
                    // comp_rate = (fee_percentage - redline) × (configured_commission / 100)
                    // total_commission = (comp_rate / 100) × GAV
                    $feePercentage = $netEpc * 100;
                    $compRate = ($feePercentage - $redline) * ($configuredAmount / 100);
                    $commission = ($compRate / 100) * $grossAccountValue;
                    // No tooltip for standard commission types - only custom fields get tooltips
                } elseif ($isPestCompany) {
                    // Pest/Fiber: (percent / 100) × GAV × CM
                    $commission = ($configuredAmount / 100) * $grossAccountValue * $cm;
                } elseif ($isTurfCompany) {
                    // Turf: (GAV + ((Net EPC - Redline) × CM × KW × 1000)) × percent / 100
                    $result = (($grossAccountValue + (($netEpc - $redline) * $cm) * $systemSize * 1000) * $configuredAmount / 100);
                    $commission = max(0, $result);
                } elseif ($isRoofingCompany) {
                    // Roofing: GAV-based formula
                    $commission = ($configuredAmount / 100) * $grossAccountValue * $cm;
                } else {
                    // Solar (default): ((Net EPC - Redline) × CM × KW × 1000 × percent) / 100
                    $result = (($netEpc - $redline) * $cm * $systemSize * 1000 * $configuredAmount) / 100;
                    $commission = max(0, $result);
                }
                break;

            case 'custom field':
                $commission = 0;
                break;

            default:
                $commission = 0;
                break;
        }

        return [
            'commission' => $commission,
            'tooltip' => $tooltip
        ];
    }

    /**
     * Build a human-readable formula expression from a custom field's formula.
     */
    public function buildFormulaExpression(Crmcustomfields $customField): string
    {
        if (!$customField->is_calculated || !$customField->formula) {
            return $customField->name;
        }

        $formula = $customField->formula;

        if (!isset($formula['operands']) || !isset($formula['operator'])) {
            return $customField->name;
        }

        $operands = $formula['operands'];
        $operator = $formula['operator'];

        // Map operator to symbol
        $operatorSymbols = [
            'add' => '+',
            'subtract' => '-',
            'multiply' => '×',
            'divide' => '÷',
        ];
        $symbol = $operatorSymbols[$operator] ?? '+';

        // Build operand strings
        $operandStrings = [];
        foreach ($operands as $operand) {
            $type = $operand['type'] ?? null;

            if ($type === 'constant') {
                $value = $operand['value'] ?? 0;
                $operandStrings[] = is_float($value) ? number_format($value, 2) : (string) $value;
            } elseif ($type === 'field') {
                $key = $operand['key'] ?? null;
                $fieldId = $operand['field_id'] ?? null;

                if ($key) {
                    // System field
                    $operandStrings[] = str_replace('_', ' ', $key);
                } elseif ($fieldId) {
                    // Custom field reference
                    $referencedField = $this->getCachedField($fieldId);
                    $operandStrings[] = $referencedField ? $referencedField->name : 'field_' . $fieldId;
                }
            }
        }

        // Build expression
        if (count($operandStrings) >= 2) {
            return '(' . implode(' ' . $symbol . ' ', $operandStrings) . ')';
        } elseif (count($operandStrings) == 1) {
            return '(' . $operandStrings[0] . ')';
        }

        return $customField->name;
    }
}
