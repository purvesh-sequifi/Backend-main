<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Crmcustomfields Model
 * 
 * Represents custom sales fields that can be configured per company
 * for use in commission, override, and upfront calculations.
 * 
 * Table: crmsale_custom_field
 * 
 * Schema:
 * - id, name, type, value (JSON), visiblecustomer, status, sort_order,
 *   field_category, company_id, created_at, updated_at
 * 
 * The 'value' JSON column stores: is_calculated, is_available_in_position, formula
 */
class Crmcustomfields extends Model
{
    /**
     * The table associated with the model.
     * Using existing crmsale_custom_field table
     */
    protected $table = 'crmsale_custom_field';

    protected $fillable = [
        'name',
        'type',
        'value',
        'visiblecustomer',
        'status',
        'sort_order',
        'field_category',
    ];

    protected $casts = [
        'value' => 'array',
        'visiblecustomer' => 'integer',
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Accessor: Get is_calculated from value JSON
     */
    public function getIsCalculatedAttribute(): bool
    {
        return (bool) ($this->value['is_calculated'] ?? false);
    }

    /**
     * Accessor: Get is_available_in_position from value JSON
     */
    public function getIsAvailableInPositionAttribute(): bool
    {
        return (bool) ($this->value['is_available_in_position'] ?? false);
    }

    /**
     * Accessor: Get formula from value JSON
     */
    public function getFormulaAttribute(): ?array
    {
        return $this->value['formula'] ?? null;
    }

    /**
     * Get the company that owns this custom field
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_id');
    }

    /**
     * Scope to get only active custom fields
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get only fields available for position settings
     * Uses JSON extraction for the value column
     */
    public function scopeAvailableInPosition($query)
    {
        return $query->whereRaw("JSON_EXTRACT(value, '$.is_available_in_position') = true");
    }

    /**
     * Scope to get only number type fields (for calculations)
     */
    public function scopeNumberType($query)
    {
        return $query->where('type', 'number');
    }

    /**
     * Scope to filter by company
     * Note: The crmsale_custom_field table doesn't have a company_id column.
     * In single-tenant deployments, all custom fields belong to the same company.
     * This scope is kept for API compatibility but doesn't actually filter.
     */
    public function scopeForCompany($query, $companyId)
    {
        // The table doesn't have company_id column, so we just return the query as-is
        // In single-tenant mode, all custom fields belong to the same company
        return $query;
    }

    /**
     * Check if this custom field is currently in use
     */
    public function isInUse(): bool
    {
        // Check position tables
        $positionUsage = 
            PositionCommission::where('custom_sales_field_id', $this->id)->exists() ||
            PositionOverride::where('direct_custom_sales_field_id', $this->id)
                ->orWhere('indirect_custom_sales_field_id', $this->id)
                ->orWhere('office_custom_sales_field_id', $this->id)->exists() ||
            PositionCommissionUpfronts::where('custom_sales_field_id', $this->id)->exists();

        if ($positionUsage) {
            return true;
        }

        // Check user tables
        $userUsage = User::where('commission_custom_sales_field_id', $this->id)
            ->orWhere('self_gen_commission_custom_sales_field_id', $this->id)
            ->orWhere('upfront_custom_sales_field_id', $this->id)
            ->orWhere('direct_custom_sales_field_id', $this->id)
            ->orWhere('indirect_custom_sales_field_id', $this->id)
            ->orWhere('office_custom_sales_field_id', $this->id)->exists();

        if ($userUsage) {
            return true;
        }

        // Check override tables
        $overrideUsage = 
            ManualOverrides::where('custom_sales_field_id', $this->id)->exists() ||
            UserOverrides::where('custom_sales_field_id', $this->id)->exists();

        if ($overrideUsage) {
            return true;
        }

        // Check onboarding tables
        $onboardingUsage = 
            OnboardingEmployees::where('commission_custom_sales_field_id', $this->id)
                ->orWhere('self_gen_commission_custom_sales_field_id', $this->id)
                ->orWhere('upfront_custom_sales_field_id', $this->id)
                ->orWhere('direct_custom_sales_field_id', $this->id)
                ->orWhere('indirect_custom_sales_field_id', $this->id)
                ->orWhere('office_custom_sales_field_id', $this->id)->exists();

        if ($onboardingUsage) {
            return true;
        }

        // Only check sales values for 'number' type fields
        // Text and date fields can be archived even if they have values in sales
        // (existing values will still be displayed, but new sales won't show the field)
        if ($this->type === 'number') {
            $salesWithValues = Crmsaleinfo::whereNotNull('custom_field_values')
                ->whereRaw('JSON_CONTAINS_PATH(custom_field_values, "one", ?)', ['$."' . (int) $this->id . '"'])
                ->exists();

            if ($salesWithValues) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get detailed usage information for this custom field (LEGACY METHOD)
     * 
     * This checks the old usage patterns (position tables, user tables, etc.)
     * Used when Custom Sales Fields feature is NOT enabled.
     * 
     * @return array Usage details array
     */
    public function getUsageDetails(): array
    {
        $usage = [];

        // Get positions using this custom field with their names
        $positions = PositionCommission::with('position:id,position_name')
            ->where('custom_sales_field_id', $this->id)
            ->get();
        
        if ($positions->count() > 0) {
            $usage['position_commissions'] = [
                'count' => $positions->count(),
                'positions' => $positions->map(fn($pc) => [
                    'id' => $pc->position_id,
                    'name' => $pc->position?->position_name ?? 'Unknown Position',
                ])->unique('id')->values()->toArray(),
            ];
        }

        $userCount = User::where('commission_custom_sales_field_id', $this->id)
            ->orWhere('self_gen_commission_custom_sales_field_id', $this->id)
            ->orWhere('upfront_custom_sales_field_id', $this->id)
            ->orWhere('direct_custom_sales_field_id', $this->id)
            ->orWhere('indirect_custom_sales_field_id', $this->id)
            ->orWhere('office_custom_sales_field_id', $this->id)->count();
        if ($userCount > 0) {
            $usage['users'] = $userCount;
        }

        $overrideCount = UserOverrides::where('custom_sales_field_id', $this->id)->count();
        if ($overrideCount > 0) {
            $usage['user_overrides'] = $overrideCount;
        }

        // Only count sales values for 'number' type fields (text/date can be archived with values)
        if ($this->type === 'number') {
            $salesCount = Crmsaleinfo::whereNotNull('custom_field_values')
                ->whereRaw('JSON_CONTAINS_PATH(custom_field_values, "one", ?)', ['$."' . (int) $this->id . '"'])
                ->count();
            if ($salesCount > 0) {
                $usage['sales_with_values'] = $salesCount;
            }
        }

        return $usage;
    }

    /**
     * Get detailed usage information for a custom field.
     * 
     * When Custom Sales Fields feature is ENABLED:
     * - Returns position-based usage (which positions use this field)
     * - Includes sales_with_values count
     * 
     * When Custom Sales Fields feature is DISABLED (legacy mode):
     * - Returns user-based usage (which users have this field configured)
     * - Maintains backward compatibility with existing functionality
     * 
     * @return array ['is_used' => bool, 'usage' => array]
     */
    public function getCustomFieldUsageDetails(): array
    {
        // Check if Custom Sales Fields feature is enabled for this company
        $companyProfile = \App\Models\CompanyProfile::find($this->company_id);
        $isCustomSalesFieldsEnabled = $companyProfile && 
            \Laravel\Pennant\Feature::for($companyProfile)->active(\App\Features\CustomSalesFieldsFeature::NAME);
        
        if ($isCustomSalesFieldsEnabled) {
            return $this->getPositionBasedUsageDetails();
        }
        
        // Legacy mode: return user-based usage for backward compatibility
        return $this->getUserBasedUsageDetails();
    }
    
    /**
     * Get position-based usage details (for Custom Sales Fields feature)
     * 
     * Checks if the field is used in:
     * - Position commission configurations
     * - Position override configurations  
     * - Position upfront configurations (including tiers)
     * - Sales with stored values
     * 
     * @return array ['is_used' => bool, 'usage' => array]
     */
    protected function getPositionBasedUsageDetails(): array
    {
        $fieldId = $this->id;
        $usage = [];
        
        // Collect all positions using this custom field
        $positionIds = collect();
        
        // 1. Check PositionCommission - where commission_amount_type = 'custom field' 
        //    AND custom_sales_field_id matches
        $commissionPositionIds = \App\Models\PositionCommission::where('custom_sales_field_id', $fieldId)
            ->where('commission_amount_type', 'custom field')
            ->whereNotNull('position_id')
            ->pluck('position_id');
        $positionIds = $positionIds->merge($commissionPositionIds);
        
        // 2. Check TiersPositionCommission - tiers can also use custom fields
        $tiersCommissionPositionIds = \App\Models\TiersPositionCommission::where('custom_sales_field_id', $fieldId)
            ->where('commission_type', 'custom field')
            ->whereNotNull('position_id')
            ->pluck('position_id');
        $positionIds = $positionIds->merge($tiersCommissionPositionIds);
        
        // 3. Check PositionOverride - check all three custom field columns
        $overridePositionIds = \App\Models\PositionOverride::where(function ($query) use ($fieldId) {
                $query->where('direct_custom_sales_field_id', $fieldId)
                      ->orWhere('indirect_custom_sales_field_id', $fieldId)
                      ->orWhere('office_custom_sales_field_id', $fieldId);
            })
            ->where('type', 'custom field')
            ->whereNotNull('position_id')
            ->pluck('position_id');
        $positionIds = $positionIds->merge($overridePositionIds);
        
        // 4. TiersPositionOverrides does not have custom field columns, 
        //    so we skip it (overrides with tiers don't support custom fields)
        
        // 5. Check PositionCommissionUpfronts - where calculated_by = 'custom field'
        $upfrontPositionIds = \App\Models\PositionCommissionUpfronts::where('custom_sales_field_id', $fieldId)
            ->where('calculated_by', 'custom field')
            ->whereNotNull('position_id')
            ->pluck('position_id');
        $positionIds = $positionIds->merge($upfrontPositionIds);
        
        // 6. Check TiersPositionUpfront
        $tiersUpfrontPositionIds = \App\Models\TiersPositionUpfront::where('custom_sales_field_id', $fieldId)
            ->where('upfront_type', 'custom field')
            ->whereNotNull('position_id')
            ->pluck('position_id');
        $positionIds = $positionIds->merge($tiersUpfrontPositionIds);
        
        // Get unique position IDs
        $uniquePositionIds = $positionIds->unique()->values()->toArray();
        
        // Build position_commissions response
        if (!empty($uniquePositionIds)) {
            $positions = \App\Models\Positions::whereIn('id', $uniquePositionIds)
                ->get(['id', 'position_name'])
                ->map(function ($pos) {
                    return [
                        'id' => $pos->id,
                        'name' => $pos->position_name ?? 'Unknown Position',
                    ];
                })
                ->values()
                ->toArray();
            
            $usage['position_commissions'] = [
                'count' => count($positions),
                'positions' => $positions,
            ];
        }
        
        // 7. Count sales with stored values for this field
        // Custom fields are company-specific, so if a sale has this field's value stored,
        // it's from the same company. We just count directly without company join.
        $salesWithValuesCount = \App\Models\Crmsaleinfo::whereNotNull('custom_field_values')
            ->whereRaw("JSON_CONTAINS_PATH(custom_field_values, 'one', ?)", ['$."' . (int) $fieldId . '"'])
            ->count();
        
        if ($salesWithValuesCount > 0) {
            $usage['sales_with_values'] = $salesWithValuesCount;
        }
        
        $isUsed = !empty($usage['position_commissions']) || !empty($usage['sales_with_values']);
        
        return [
            'is_used' => $isUsed,
            'usage' => $usage,
        ];
    }
    
    /**
     * Get user-based usage details (legacy mode for backward compatibility)
     * 
     * Checks if the field is used in:
     * - Other calculated field formulas (as an operand)
     * - User commission configurations (UserCommissionHistory)
     * - User override configurations (UserOverrideHistory)
     * - User upfront configurations (UserUpfrontHistory)
     * 
     * @return array ['is_used' => bool, 'usage' => array]
     */
    protected function getUserBasedUsageDetails(): array
    {
        $fieldId = $this->id;
        $usage = [
            'formulas' => [],
            'commissions' => [],
            'overrides' => [],
            'upfronts' => [],
        ];
        
        // 1. Check if used in other calculated field formulas
        $fieldsUsingInFormula = self::where('status', 1)
            ->where('id', '!=', $fieldId)
            ->whereRaw("JSON_EXTRACT(value, '$.is_calculated') = true")
            ->whereNotNull('value')
            ->get()
            ->filter(function ($field) use ($fieldId) {
                $formula = $field->formula;
                if (!is_array($formula) || !isset($formula['operands'])) {
                    return false;
                }
                
                foreach ($formula['operands'] as $operand) {
                    if (
                        isset($operand['type']) && 
                        $operand['type'] === 'field'
                    ) {
                        // Check both 'key' format (custom_field_X) and 'field_id' format (direct ID)
                        $matchesKey = isset($operand['key']) && $operand['key'] === "custom_field_{$fieldId}";
                        $matchesFieldId = isset($operand['field_id']) && $operand['field_id'] == $fieldId;
                        
                        if ($matchesKey || $matchesFieldId) {
                            return true;
                        }
                    }
                }
                return false;
            });
        
        if ($fieldsUsingInFormula->isNotEmpty()) {
            $usage['formulas'] = $fieldsUsingInFormula->pluck('name', 'id')->toArray();
        }
        
        // 2. Check if used in commission configurations (UserCommissionHistory)
        $commissionUserIds = \App\Models\UserCommissionHistory::where('custom_sales_field_id', $fieldId)
            ->where('commission_type', 'custom field')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('user_id')
            ->toArray();
        
        if (!empty($commissionUserIds)) {
            $users = User::whereIn('id', $commissionUserIds)
                ->get(['id', 'first_name', 'last_name']);
            
            $usage['commissions'] = $users->mapWithKeys(function($user) {
                $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                return [$user->id => $userName ?: 'Unknown User'];
            })->toArray();
        }
        
        // 3. Check if used in override configurations (UserOverrideHistory)
        $overrideUserIds = \App\Models\UserOverrideHistory::where(function($query) use ($fieldId) {
                $query->where('direct_custom_sales_field_id', $fieldId)
                      ->orWhere('indirect_custom_sales_field_id', $fieldId)
                      ->orWhere('office_custom_sales_field_id', $fieldId);
            })
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('user_id')
            ->toArray();
        
        if (!empty($overrideUserIds)) {
            $users = User::whereIn('id', $overrideUserIds)
                ->get(['id', 'first_name', 'last_name']);
            
            $usage['overrides'] = $users->mapWithKeys(function($user) {
                $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                return [$user->id => $userName ?: 'Unknown User'];
            })->toArray();
        }
        
        // 4. Check if used in upfront configurations (UserUpfrontHistory)
        $upfrontUserIds = \App\Models\UserUpfrontHistory::where('custom_sales_field_id', $fieldId)
            ->where('upfront_sale_type', 'custom field')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('user_id')
            ->toArray();
        
        if (!empty($upfrontUserIds)) {
            $users = User::whereIn('id', $upfrontUserIds)
                ->get(['id', 'first_name', 'last_name']);
            
            $usage['upfronts'] = $users->mapWithKeys(function($user) {
                $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                return [$user->id => $userName ?: 'Unknown User'];
            })->toArray();
        }
        
        $isUsed = !empty($usage['formulas']) || 
                  !empty($usage['commissions']) || 
                  !empty($usage['overrides']) || 
                  !empty($usage['upfronts']);
        
        return [
            'is_used' => $isUsed,
            'usage' => $usage,
        ];
    }

    /**
     * Build the value JSON from individual fields
     */
    public static function buildValueJson(bool $isCalculated = false, bool $isAvailableInPosition = false, ?array $formula = null): array
    {
        $value = [
            'is_calculated' => $isCalculated,
            'is_available_in_position' => $isAvailableInPosition,
        ];

        if ($formula !== null) {
            $value['formula'] = $formula;
        }

        return $value;
    }
}
