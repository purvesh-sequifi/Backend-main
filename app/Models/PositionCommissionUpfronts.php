<?php

namespace App\Models;

use App\Services\SalesCalculationContext;
use App\Services\SalesCustomFieldCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PositionCommissionUpfronts extends Model
{
    use HasFactory;

    protected $table = 'position_commission_upfronts';

    /**
     * Boot the model and register the retrieved event for custom field conversion.
     *
     * This implements the "Trick Subroutine" approach for upfronts:
     * - When an upfront is fetched with calculated_by = 'custom field'
     * - AND the company has the feature enabled
     * - AND a sale context is set
     * - Auto-convert to 'per sale' with calculated amount
     *
     * This ensures ZERO modifications to SubroutineTrait.
     */
    protected static function booted(): void
    {
        static::retrieved(function (PositionCommissionUpfronts $upfront) {
            // EARLY EXIT: Check type FIRST before any context checks
            // This prevents unnecessary context/feature flag checks for most upfronts
            if ($upfront->calculated_by !== 'custom field') {
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
                $upfront->calculated_by = 'per sale';
                $upfront->upfront_ammount = 0;
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

            $configuredAmount = $upfront->upfront_ammount ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;

            // Store metadata in model's attributes array (PHP 8.2 compatible)
            $upfront->setAttribute('_original_type', 'custom field');
            $upfront->setAttribute('_custom_field_value', $customFieldValue);
            $upfront->setAttribute('_custom_field_id', $upfront->custom_sales_field_id);
            $upfront->setAttribute('_configured_amount', $configuredAmount);

            // Convert to 'per sale' with calculated amount (not persisted to DB)
            $upfront->calculated_by = 'per sale';
            $upfront->upfront_ammount = $calculatedAmount;
        });

        // Auto-create NULL effective_date fallback for backdata sales when saving custom field records
        // This follows the same pattern as existing non-custom-field records in SubroutineTrait
        static::saved(function (PositionCommissionUpfronts $record) {
            // Only for custom field type with a specific effective_date
            if ($record->calculated_by === 'custom field'
                && $record->effective_date !== null
                && $record->custom_sales_field_id) {

                static::ensureBackdataFallback(
                    $record->position_id,
                    $record->product_id,
                    $record->custom_sales_field_id
                );
            }
        });
    }

    /**
     * Ensure a fallback (NULL effective_date) record exists for backdata sales.
     *
     * This follows the same pattern as existing non-custom-field records.
     * SubroutineTrait checks: effective_date <= sale_date, then falls back to NULL effective_date.
     * Without a NULL fallback, sales dated before the earliest effective_date get no upfront.
     *
     * @param int $positionId The position ID
     * @param int $productId The product ID
     * @param int|null $customFieldId The custom sales field ID
     * @return void
     */
    public static function ensureBackdataFallback(int $positionId, int $productId, ?int $customFieldId): void
    {
        // Use database transaction with locking to prevent race conditions
        // where two simultaneous requests could both pass the exists check
        DB::transaction(function () use ($positionId, $productId, $customFieldId) {
            // Check if a NULL effective_date custom field record already exists for this position/product
            // Use lockForUpdate to prevent race conditions
            $fallbackExists = static::where('position_id', $positionId)
                ->where('product_id', $productId)
                ->whereNull('effective_date')
                ->where('calculated_by', 'custom field')
                ->lockForUpdate()
                ->exists();

            if ($fallbackExists) {
                return; // Already has custom field fallback
            }

            // Get the earliest CUSTOM FIELD record to copy settings from (for backdata)
            $earliestCustomFieldRecord = static::where('position_id', $positionId)
                ->where('product_id', $productId)
                ->where('calculated_by', 'custom field')
                ->whereNotNull('effective_date')
                ->whereNotNull('custom_sales_field_id')
                ->orderBy('effective_date', 'asc')
                ->first();

            if (!$earliestCustomFieldRecord) {
                return; // No custom field records to copy from
            }

            // Create NULL effective_date fallback (for sales before earliest date)
            // Use withoutEvents to prevent recursion
            static::withoutEvents(function () use ($positionId, $productId, $customFieldId, $earliestCustomFieldRecord) {
                static::create([
                    'position_id' => $positionId,
                    'product_id' => $productId,
                    'upfront_status' => $earliestCustomFieldRecord->upfront_status,
                    'effective_date' => null, // NULL = fallback for all dates
                    'calculated_by' => 'custom field', // Explicitly set as custom field
                    'custom_sales_field_id' => $customFieldId ?? $earliestCustomFieldRecord->custom_sales_field_id,
                    'upfront_ammount' => $earliestCustomFieldRecord->upfront_ammount ?? 0,
                    'upfront_ammount_locked' => $earliestCustomFieldRecord->upfront_ammount_locked ?? 0,
                    'calculated_locked' => $earliestCustomFieldRecord->calculated_locked ?? 0,
                    'upfront_system' => $earliestCustomFieldRecord->upfront_system,
                    'upfront_system_locked' => $earliestCustomFieldRecord->upfront_system_locked ?? 0,
                    'upfront_limit' => $earliestCustomFieldRecord->upfront_limit,
                    'upfront_limit_type' => $earliestCustomFieldRecord->upfront_limit_type,
                ]);
            });
        });
    }

    /**
     * Backfill NULL effective_date fallbacks for all existing custom field upfront records.
     *
     * Run this once via artisan tinker or a command to fix existing data:
     * \App\Models\PositionCommissionUpfronts::backfillCustomFieldFallbacks();
     *
     * @return array Summary of what was created
     */
    public static function backfillCustomFieldFallbacks(): array
    {
        $created = 0;
        $skipped = 0;

        // Find all unique position/product combinations with custom field but potentially no NULL fallback
        $combinations = static::where('calculated_by', 'custom field')
            ->whereNotNull('effective_date')
            ->whereNotNull('custom_sales_field_id')
            ->select('position_id', 'product_id', 'custom_sales_field_id')
            ->distinct()
            ->get();

        foreach ($combinations as $combo) {
            // Check if fallback already exists
            $fallbackExists = static::where('position_id', $combo->position_id)
                ->where('product_id', $combo->product_id)
                ->whereNull('effective_date')
                ->where('calculated_by', 'custom field')
                ->exists();

            if ($fallbackExists) {
                $skipped++;
                continue;
            }

            static::ensureBackdataFallback(
                $combo->position_id,
                $combo->product_id,
                $combo->custom_sales_field_id
            );
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total_combinations' => $combinations->count(),
        ];
    }

    protected $fillable = [
        'position_id',
        'core_position_id',
        'product_id',
        'milestone_schema_id',
        'milestone_schema_trigger_id',
        'self_gen_user',
        'status_id',
        'upfront_ammount',
        'upfront_ammount_locked',
        'calculated_by',
        'calculated_locked',
        'upfront_status',
        'upfront_system',
        'upfront_system_locked',
        'upfront_limit',
        'upfront_limit_type',
        'tiers_advancement',
        'tiers_id',
        'tiers_hiring_locked',
        'effective_date',
        'deductible_from_prior',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tierDetail(): HasOne
    {
        return $this->hasOne(\App\Models\PositionTierOverride::class, 'id', 'position_id');
    }

    public function milestoneHistory(): HasOne
    {
        return $this->hasOne(ProductMilestoneHistories::class, 'id', 'milestone_schema_id');
    }

    public function milestoneSchema(): HasOne
    {
        return $this->hasOne(MilestoneSchema::class, 'id', 'milestone_schema_id');
    }

    public function milestoneTrigger(): HasOne
    {
        return $this->hasOne(MilestoneSchemaTrigger::class, 'id', 'milestone_schema_trigger_id');
    }

    public function tiersRange(): HasMany
    {
        return $this->hasMany(TiersPositionUpfront::class, 'position_upfront_id', 'id')->with('tiersSchema');
    }

    /**
     * Get the custom sales field for this upfront
     */
    public function customSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
