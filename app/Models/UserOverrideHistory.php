<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use App\Services\SalesCalculationContext;
use App\Services\SalesCustomFieldCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserOverrideHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_override_history';

    /**
     * Boot the model and register the retrieved event for custom field conversion.
     *
     * This implements the "Trick Subroutine" approach for overrides.
     * Handles direct, indirect, and office override types - each with their own custom field ID.
     */
    protected static function booted(): void
    {
        static::retrieved(function (UserOverrideHistory $override) {
            // EARLY EXIT: Check if ANY override type is 'custom field' FIRST before context checks
            // This prevents unnecessary context/feature flag checks for most overrides
            $hasCustomFieldOverride = 
                ($override->direct_overrides_type === 'custom field' && !empty($override->direct_custom_sales_field_id)) ||
                ($override->indirect_overrides_type === 'custom field' && !empty($override->indirect_custom_sales_field_id)) ||
                ($override->office_overrides_type === 'custom field' && !empty($override->office_custom_sales_field_id));
            
            if (!$hasCustomFieldOverride) {
                return;
            }

            // Skip if no context is set (not in a subroutine calculation)
            if (!SalesCalculationContext::hasContext()) {
                return;
            }

            // SAFETY: If feature is disabled but type is 'custom field', convert to 'per sale' with $0
            // This prevents SubroutineOverrideTrait from treating it as 'percent' type
            // which would cause wrong calculations using the percent formula
            if (!SalesCalculationContext::isCustomFieldsEnabled()) {
                // Convert all custom field overrides to 'per sale' with $0
                if ($override->direct_overrides_type === 'custom field') {
                    $override->direct_overrides_type = 'per sale';
                    $override->direct_overrides_amount = 0;
                }
                if ($override->indirect_overrides_type === 'custom field') {
                    $override->indirect_overrides_type = 'per sale';
                    $override->indirect_overrides_amount = 0;
                }
                if ($override->office_overrides_type === 'custom field') {
                    $override->office_overrides_type = 'per sale';
                    $override->office_overrides_amount = 0;
                }
                return;
            }

            $saleId = SalesCalculationContext::getSaleId();
            if (!$saleId) {
                return;
            }

            $calculator = app(SalesCustomFieldCalculator::class);
            
            // Get Commission Modifier (CM) from company profile (already in context, no extra query)
            $companyProfile = SalesCalculationContext::getCompanyProfile();
            $cm = 1.0; // Default CM is 1.0 (100%)
            if ($companyProfile && !empty($companyProfile->company_margin)) {
                $cm = 1 - ((float) $companyProfile->company_margin / 100);
            }
            
            // IMPORTANT: SubroutineTrait behavior varies by company type
            // For Pest company types: 'per sale' overrides do NOT have CM applied
            // For other types (Solar, etc.): 'per sale' overrides DO have CM applied via $x
            // We only pre-compensate for CM if the company type applies CM to overrides
            $isPestCompany = $companyProfile && in_array(
                $companyProfile->company_type,
                \App\Models\CompanyProfile::PEST_COMPANY_TYPE
            );
            $shouldApplyCmCompensation = !$isPestCompany && $cm > 0 && $cm < 1;
            
            // NOTE: SubroutineTrait does NOT halve overrides (unlike commissions/upfronts)
            // Overrides are calculated as-is without division by 2 for multi-rep sales
            // Therefore, NO halving compensation is needed for custom field overrides

            // Convert direct override if it's custom field type AND has a custom field ID
            if ($override->direct_overrides_type === 'custom field' && !empty($override->direct_custom_sales_field_id)) {
                $customFieldValue = $calculator->getCustomFieldValue(
                    $saleId,
                    $override->direct_custom_sales_field_id
                ) ?? 0;
                $configuredAmount = $override->direct_overrides_amount ?? 0;
                $calculatedAmount = $configuredAmount * $customFieldValue;
                
                // Pre-compensate for CM only if company type applies CM to overrides
                // Pest companies do NOT apply CM to 'per sale' overrides
                if ($shouldApplyCmCompensation) {
                    $calculatedAmount = $calculatedAmount / $cm;
                }
                
                // Store metadata in model's attributes array (PHP 8.2 compatible)
                $override->setAttribute('_original_direct_type', 'custom field');
                $override->setAttribute('_direct_configured_amount', $configuredAmount);
                $override->setAttribute('_direct_custom_field_value', $customFieldValue);
                $override->setAttribute('_direct_custom_field_id', $override->direct_custom_sales_field_id);
                $override->direct_overrides_type = 'per sale';
                $override->direct_overrides_amount = $calculatedAmount;
            }

            // Convert indirect override if it's custom field type AND has a custom field ID
            if ($override->indirect_overrides_type === 'custom field' && !empty($override->indirect_custom_sales_field_id)) {
                $customFieldValue = $calculator->getCustomFieldValue(
                    $saleId,
                    $override->indirect_custom_sales_field_id
                ) ?? 0;
                $configuredAmount = $override->indirect_overrides_amount ?? 0;
                $calculatedAmount = $configuredAmount * $customFieldValue;
                
                // Pre-compensate for CM only if company type applies CM to overrides
                // Pest companies do NOT apply CM to 'per sale' overrides
                if ($shouldApplyCmCompensation) {
                    $calculatedAmount = $calculatedAmount / $cm;
                }
                
                // Store metadata in model's attributes array (PHP 8.2 compatible)
                $override->setAttribute('_original_indirect_type', 'custom field');
                $override->setAttribute('_indirect_configured_amount', $configuredAmount);
                $override->setAttribute('_indirect_custom_field_value', $customFieldValue);
                $override->setAttribute('_indirect_custom_field_id', $override->indirect_custom_sales_field_id);
                $override->indirect_overrides_type = 'per sale';
                $override->indirect_overrides_amount = $calculatedAmount;
            }

            // Convert office override if it's custom field type AND has a custom field ID
            if ($override->office_overrides_type === 'custom field' && !empty($override->office_custom_sales_field_id)) {
                $customFieldValue = $calculator->getCustomFieldValue(
                    $saleId,
                    $override->office_custom_sales_field_id
                ) ?? 0;
                $configuredAmount = $override->office_overrides_amount ?? 0;
                $calculatedAmount = $configuredAmount * $customFieldValue;
                
                // Pre-compensate for CM only if company type applies CM to overrides
                // Pest companies do NOT apply CM to 'per sale' overrides
                if ($shouldApplyCmCompensation) {
                    $calculatedAmount = $calculatedAmount / $cm;
                }
                
                // Store metadata in model's attributes array (PHP 8.2 compatible)
                $override->setAttribute('_original_office_type', 'custom field');
                $override->setAttribute('_office_configured_amount', $configuredAmount);
                $override->setAttribute('_office_custom_field_value', $customFieldValue);
                $override->setAttribute('_office_custom_field_id', $override->office_custom_sales_field_id);
                $override->office_overrides_type = 'per sale';
                $override->office_overrides_amount = $calculatedAmount;
            }
        });
    }

    protected $fillable = [
        'user_id',
        'updater_id',
        'product_id',
        'override_effective_date',
        'effective_end_date',
        'direct_overrides_amount',
        'direct_overrides_type',
        'indirect_overrides_amount',
        'indirect_overrides_type',
        'office_overrides_amount',
        'office_overrides_type',
        'office_stack_overrides_amount',
        'old_product_id',
        'old_direct_overrides_amount',
        'old_direct_overrides_type',
        'old_indirect_overrides_amount',
        'old_indirect_overrides_type',
        'old_office_overrides_amount',
        'old_office_overrides_type',
        'old_office_stack_overrides_amount',
        'direct_tiers_id',
        'old_direct_tiers_id',
        'indirect_tiers_id',
        'old_indirect_tiers_id',
        'office_tiers_id',
        'old_office_tiers_id',
        'action_item_status',
        // Custom Sales Field feature - separate IDs for each override type
        'direct_custom_sales_field_id',
        'indirect_custom_sales_field_id',
        'office_custom_sales_field_id',
        'custom_sales_field_id', // Legacy/fallback column
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at'
    ];

    public function useroverridehistory(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'email', 'image', 'office_id', 'state_id');
    }

    public function useroverride(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'email', 'image', 'office_id', 'state_id');
    }

    public function userAdditionalOfficeHistory(): HasMany
    {
        return $this->hasMany(UserAdditionalOfficeOverrideHistory::class, 'user_id', 'user_id');
    }

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id');
    }

    public function directTiers(): HasMany
    {
        return $this->hasMany(UserDirectOverrideHistoryTiersRange::class, 'user_override_history_id', 'id')->with('tiersSchema');
    }

    public function indirectTiers(): HasMany
    {
        return $this->hasMany(UserIndirectOverrideHistoryTiersRange::class, 'user_override_history_id', 'id')->with('tiersSchema');
    }

    public function officeTiers(): HasMany
    {
        return $this->hasMany(UserOfficeOverrideHistoryTiersRange::class, 'user_office_override_history_id', 'id')->with('tiersSchema');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    /**
     * Get the custom sales field for this override history
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
