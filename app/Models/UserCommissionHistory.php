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

class UserCommissionHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_commission_history';

    /**
     * Boot the model and register the retrieved event for custom field conversion.
     *
     * This implements the "Trick Subroutine" approach:
     * - When a user commission is fetched with type 'custom field'
     * - AND the company has the feature enabled
     * - AND a sale context is set
     * - Auto-convert to 'per sale' with calculated amount
     *
     * This ensures ZERO modifications to SubroutineTrait.
     */
    protected static function booted(): void
    {
        static::retrieved(function (UserCommissionHistory $commission) {
            // EARLY EXIT: Check commission type FIRST before any context/DB queries
            // This prevents N+1 queries when loading lists of commissions
            if ($commission->commission_type !== 'custom field') {
                return;
            }

            // Must have a custom field ID
            if (empty($commission->custom_sales_field_id)) {
                return;
            }

            // PATH 1: No subroutine context - handle display/report scenarios
            if (!SalesCalculationContext::hasContext()) {
                self::handleDisplayContext($commission);
                return;
            }

            // SAFETY: If feature is disabled but type is 'custom field', convert to 'per sale' with $0
            // This prevents SubroutineTrait from treating it as 'percent' type (fallback in else block)
            // which would cause wrong calculations using the percent formula
            if (!SalesCalculationContext::isCustomFieldsEnabled()) {
                $commission->commission_type = 'per sale';
                $commission->commission = 0;
                $commission->setAttribute('_original_type', 'custom field');
                $commission->setAttribute('_feature_disabled', true);
                return;
            }

            $saleId = SalesCalculationContext::getSaleId();
            if (!$saleId) {
                return;
            }

            // Perform the trick conversion
            $calculator = app(SalesCustomFieldCalculator::class);
            $customFieldValue = $calculator->getCustomFieldValue(
                $saleId,
                $commission->custom_sales_field_id
            ) ?? 0;

            $configuredAmount = $commission->commission ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;

            // Get the company profile for CM compensation
            $companyProfile = SalesCalculationContext::getCompanyProfile();
            $cm = 1.0; // Default CM is 1.0 (100%)
            if ($companyProfile && !empty($companyProfile->company_margin)) {
                // Validate margin is within valid range (0-99.99) to prevent:
                // - margin = 100 → $cm = 0 → division by zero
                // - margin > 100 → $cm becomes negative → incorrect sign flip
                // - margin < 0 → $cm > 1 → compensation logic skipped
                $companyMargin = max(0, min(99.99, (float) $companyProfile->company_margin));
                $cm = 1 - ($companyMargin / 100);
            }
            
            // NOTE: We do NOT pre-compensate for halving here.
            // The subroutine halving logic is per-rep-type (closer vs setter) and depends
            // on which user is being processed. Custom fields are already per-rep configured,
            // so the subroutine's 'per sale' branch does NOT apply halving for custom fields
            // because we're providing the full calculated amount.
            
            // Custom fields should NOT have CM applied (per business requirement)
            // But since we convert to 'per sale', SubroutineTrait will apply CM
            // So we pre-compensate by dividing by CM
            // Example: $1685.25 / 0.98 = $1719.64, then SubroutineTrait applies × 0.98 = $1685.25 ✅
            if ($cm > 0 && $cm < 1) {
                $calculatedAmount = $calculatedAmount / $cm;
            }

            // Store metadata in model's attributes array (PHP 8.2 compatible)
            $commission->setAttribute('_original_type', 'custom field');
            $commission->setAttribute('_custom_field_value', $customFieldValue);
            $commission->setAttribute('_custom_field_id', $commission->custom_sales_field_id);
            $commission->setAttribute('_configured_amount', $configuredAmount);

            // Convert to 'per sale' with calculated amount (not persisted to DB)
            $commission->commission_type = 'per sale';
            $commission->commission = $calculatedAmount;
        });
    }

    protected $fillable = [
        'user_id',
        'updater_id',
        'product_id',
        'old_product_id',
        'self_gen_user',
        'old_self_gen_user',
        'commission',
        'commission_type',
        'old_commission',
        'old_commission_type',
        'commission_effective_date',
        'effective_end_date',
        'position_id',
        'core_position_id',
        'sub_position_id',
        'action_item_status',
        'tiers_id',
        'old_tiers_id',
        'custom_sales_field_id',     // Custom Sales Field feature
        'old_custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        // 'created_at',
        // 'updated_at'
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function subposition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'sub_position_id')->select('id', 'position_name');
    }

    public function position(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id')->select('id', 'position_name');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(UserCommissionHistoryTiersRange::class, 'user_commission_history_id', 'id')->with('tiersSchema');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    /**
     * Get the custom sales field associated with this commission.
     */
    public function customSalesField(): HasOne
    {
        return $this->hasOne(Crmcustomfields::class, 'id', 'custom_sales_field_id');
    }

    /**
     * Get the old custom sales field (before change).
     */
    public function oldCustomSalesField(): HasOne
    {
        return $this->hasOne(Crmcustomfields::class, 'id', 'old_custom_sales_field_id');
    }

    /**
     * Handle display context for custom field commissions.
     * 
     * This method is called when there's no full subroutine context (SalesCalculationContext),
     * typically during API responses for viewing/reporting purposes.
     * 
     * IMPORTANT: This does NOT modify the database - only in-memory attributes for display.
     * 
     * @param UserCommissionHistory $commission The commission model instance
     */
    protected static function handleDisplayContext(UserCommissionHistory $commission): void
    {
        // Check if feature is enabled using cached company profile
        $isFeatureEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled();
        
        if (!$isFeatureEnabled) {
            // PATH 1A: Feature disabled - convert to 'per sale' with $0 for safety
            // This prevents downstream code from processing custom field logic
            $commission->commission_type = 'per sale';
            $commission->commission = 0;
            $commission->setAttribute('_original_type', 'custom field');
            $commission->setAttribute('_feature_disabled', true);
            return;
        }
        
        // PATH 1B: Feature enabled but no full subroutine context (display/report)
        // Store original values in attributes for downstream code
        $originalCommission = $commission->commission;
        $commission->setAttribute('_original_type', 'custom field');
        $commission->setAttribute('_is_custom_field', true);
        $commission->setAttribute('_custom_field_id', $commission->custom_sales_field_id);
        $commission->setAttribute('_configured_amount', $originalCommission);
        
        // Get display PID from context (set explicitly by controller/service)
        // IMPORTANT: We ONLY use the context-provided PID to avoid security issues:
        // - Request parameter sniffing can use wrong PID in batch operations
        // - Prevents inconsistent behavior between requests
        // - Context is always set by controller before any commission retrieval
        $displayPid = SalesCalculationContext::getDisplayPid();
        
        if ($displayPid) {
            // Calculate custom field value for display purposes
            $calculator = app(SalesCustomFieldCalculator::class);
            $customFieldValue = $calculator->getCustomFieldValue(
                $displayPid,
                $commission->custom_sales_field_id
            ) ?? 0;
            
            $calculatedAmount = $originalCommission * $customFieldValue;
            
            $commission->setAttribute('_custom_field_value', $customFieldValue);
            $commission->setAttribute('_calculated_commission', $calculatedAmount);
            
            // Keep original commission value for "Comp Rate" display
            // Store calculated amount in attribute for "Total Commission" display
        }
        
        // Keep commission_type as 'custom field' for frontend display
        // Only convert to 'per sale' in full subroutine context (PATH 2)
    }
}
