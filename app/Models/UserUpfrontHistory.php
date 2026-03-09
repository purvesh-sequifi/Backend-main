<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use App\Services\SalesCalculationContext;
use App\Services\SalesCustomFieldCalculator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserUpfrontHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_upfront_history';

    /**
     * Boot the model and register the retrieved event for custom field conversion.
     *
     * This implements the "Trick Subroutine" approach for upfronts.
     */
    protected static function booted(): void
    {
        static::retrieved(function (UserUpfrontHistory $upfront) {
            // EARLY EXIT: Check upfront type FIRST before any context/DB queries
            // This prevents N+1 queries when loading lists of upfronts
            if ($upfront->upfront_sale_type !== 'custom field') {
                return;
            }

            // Must have a custom field ID
            if (empty($upfront->custom_sales_field_id)) {
                return;
            }

            // Skip if no context is set (not in a subroutine calculation)
            if (!SalesCalculationContext::hasContext()) {
                return;
            }

            // SAFETY: If feature is disabled but type is 'custom field', convert to 'per sale' with $0
            // This prevents SubroutineTrait from treating it as 'percent' type (fallback in else block)
            // which would cause wrong calculations using the percent formula
            if (!SalesCalculationContext::isCustomFieldsEnabled()) {
                $upfront->upfront_sale_type = 'per sale';
                $upfront->upfront_pay_amount = 0;
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
                $upfront->custom_sales_field_id
            ) ?? 0;

            $configuredAmount = $upfront->upfront_pay_amount ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;

            // Check if SubroutineTrait will apply halving logic
            // The SubroutineTrait halves upfront amounts when there are 2 reps (for 'per sale' type)
            // Since custom fields should NOT be halved (each rep has their own multiplier),
            // we need to DOUBLE the amount here so that when SubroutineTrait halves it, it returns to correct value
            
            // NOTE: SubroutineTrait does NOT apply CM to M1/upfront (only to M2/final milestones)
            // So we do NOT need CM compensation for upfronts!
            
            $willBeHalved = false;
            
            // Get the sale from context (already loaded, no extra query)
            $sale = SalesCalculationContext::getSale();
            
            if ($sale) {
                // Check if this sale has 2 closers or 2 setters (halving condition)
                $hasSecondCloser = !empty($sale->closer2_id);
                $hasSecondSetter = !empty($sale->setter2_id);
                
                if ($hasSecondCloser || $hasSecondSetter) {
                    $willBeHalved = true;
                }
                
                // If the amount will be halved by SubroutineTrait, double it now to compensate
                if ($willBeHalved) {
                    $calculatedAmount = $calculatedAmount * 2;
                }
            }

            // Store metadata in model's attributes array (PHP 8.2 compatible)
            $upfront->setAttribute('_original_type', 'custom field');
            $upfront->setAttribute('_custom_field_value', $customFieldValue);
            $upfront->setAttribute('_custom_field_id', $upfront->custom_sales_field_id);
            $upfront->setAttribute('_configured_amount', $configuredAmount);
            $upfront->setAttribute('_will_be_halved', $willBeHalved);

            // Convert to 'per sale' with calculated amount (not persisted to DB)
            $upfront->upfront_sale_type = 'per sale';
            $upfront->upfront_pay_amount = $calculatedAmount;
        });
    }

    protected $fillable = [
        'user_id',
        'updater_id',
        'product_id',
        'old_product_id',
        'milestone_schema_id',
        'old_milestone_schema_id',
        'milestone_schema_trigger_id',
        'old_milestone_schema_trigger_id',
        'self_gen_user',
        'old_self_gen_user',
        'upfront_pay_amount',
        'old_upfront_pay_amount',
        'upfront_sale_type',
        'old_upfront_sale_type',
        'upfront_effective_date',
        'effective_end_date',
        'position_id',
        'core_position_id',
        'sub_position_id',
        'action_item_status',
        'tiers_id',
        'old_tiers_id',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        // 'created_at',
        'updated_at',
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
        return $this->hasMany(UserUpfrontHistoryTiersRange::class, 'user_upfront_history_id', 'id')->with('tiersSchema');
    }

    public function schema(): HasOne
    {
        return $this->hasOne(MilestoneSchemaTrigger::class, 'id', 'milestone_schema_trigger_id');
    }

    public function tierSchema(): HasOne
    {
        return $this->hasOne(TiersSchema::class, 'id', 'tiers_id');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    protected function positionDisplayName(): Attribute
    {
        return Attribute::get(function () {
            if ($this->self_gen_user == 1) {
                return 'Self Gen';
            }

            return match ($this->core_position_id) {
                2 => 'Closer',
                3 => 'Setter',
                default => $this->position?->position_name ?? 'N/A',
            };
        });
    }

    /**
     * Get the custom sales field for this upfront history
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
