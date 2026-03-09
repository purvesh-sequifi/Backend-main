<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use App\Services\SalesCalculationContext;
use App\Services\SalesCustomFieldCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProjectionUserOverrides extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'projection_user_overrides';

    /**
     * Boot the model and register the retrieved event for custom field conversion.
     *
     * This implements the "Trick Subroutine" approach for projection overrides.
     */
    protected static function booted(): void
    {
        static::retrieved(function (ProjectionUserOverrides $override) {
            // EARLY EXIT: Check type FIRST before any context checks
            // This prevents unnecessary context/feature flag checks for most overrides
            if ($override->overrides_type !== 'custom field') {
                return;
            }

            // Must have a custom field ID
            if (empty($override->custom_sales_field_id)) {
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
                $override->overrides_type = 'per sale';
                $override->overrides_amount = 0;
                $override->total_override = 0;
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
                $override->custom_sales_field_id
            ) ?? 0;

            $configuredAmount = $override->overrides_amount ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;

            // Store metadata in model's attributes array (PHP 8.2 compatible)
            $override->setAttribute('_original_type', 'custom field');
            $override->setAttribute('_custom_field_value', $customFieldValue);
            $override->setAttribute('_custom_field_id', $override->custom_sales_field_id);
            $override->setAttribute('_configured_amount', $configuredAmount);

            // Convert to 'per sale' with calculated amount (not persisted to DB)
            $override->overrides_type = 'per sale';
            $override->overrides_amount = $calculatedAmount;
            $override->total_override = $calculatedAmount;
        });
    }

    protected $fillable = [
        'id',
        'user_id',
        'customer_name',
        'override_over',
        'type',
        'sale_user_id',
        'pid',
        'kw',
        'total_override',
        'overrides_amount',
        'overrides_type',
        'pay_period_from',
        'pay_period_to',
        'overrides_settlement_type',
        'status',
        'is_stop_payroll',
        'office_id',
        'date',
        'calculated_redline',
        'calculated_redline_type',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function userInfo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'is_manager', 'is_super_admin');
    }

    public function overrideUser(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'is_manager', 'is_super_admin');
    }

    public function overrideStatus()
    {
        return $this->hasOne('App\Models\OverrideStatus', 'user_id', 'sale_user_id')
            ->where('recruiter_id', $this->user_id)
            ->where('type', $this->type)
            ->orderByRaw('CASE WHEN product_id = 0 THEN 1 ELSE 0 END');
    }

    /**
     * Get the custom sales field for this projection override
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}