<?php

namespace App\Models;

use App\Services\SalesCalculationContext;
use App\Services\SalesCustomFieldCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PositionCommission extends Model
{
    use HasFactory;

    protected $table = 'position_commissions';

    /**
     * Boot the model and register the retrieved event for custom field conversion.
     *
     * This implements the "Trick Subroutine" approach:
     * - When a commission is fetched with type 'custom field'
     * - AND the company has the feature enabled
     * - AND a sale context is set
     * - Auto-convert to 'per sale' with calculated amount
     *
     * This ensures ZERO modifications to SubroutineTrait.
     */
    protected static function booted(): void
    {
        static::retrieved(function (PositionCommission $commission) {
            // EARLY EXIT: Check type FIRST before any context checks
            // This prevents unnecessary context/feature flag checks for most commissions
            if ($commission->commission_amount_type !== 'custom field') {
                return;
            }

            // Must have a custom field ID
            if (empty($commission->custom_sales_field_id)) {
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
                $commission->commission_amount_type = 'per sale';
                $commission->commission_parentage = 0;
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

            $configuredAmount = $commission->commission_parentage ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;

            // Store metadata in model's attributes array (PHP 8.2 compatible)
            $commission->setAttribute('_original_type', 'custom field');
            $commission->setAttribute('_custom_field_value', $customFieldValue);
            $commission->setAttribute('_custom_field_id', $commission->custom_sales_field_id);
            $commission->setAttribute('_configured_amount', $configuredAmount);

            // Convert to 'per sale' with calculated amount (not persisted to DB)
            $commission->commission_amount_type = 'per sale';
            $commission->commission_parentage = $calculatedAmount;
        });

        // Auto-create NULL effective_date fallback for backdata sales when saving custom field records
        // This follows the same pattern as existing non-custom-field records in SubroutineTrait
        static::saved(function (PositionCommission $record) {
            // Only for custom field type with a specific effective_date
            if ($record->commission_amount_type === 'custom field'
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
     * Without a NULL fallback, sales dated before the earliest effective_date get no commission.
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
                ->where('commission_amount_type', 'custom field')
                ->lockForUpdate()
                ->exists();

            if ($fallbackExists) {
                return; // Already has custom field fallback
            }

            // Get the earliest CUSTOM FIELD record to copy settings from (for backdata)
            // Important: We specifically look for custom field records, not just any record
            $earliestCustomFieldRecord = static::where('position_id', $positionId)
                ->where('product_id', $productId)
                ->where('commission_amount_type', 'custom field')
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
                    'commission_status' => $earliestCustomFieldRecord->commission_status,
                    'effective_date' => null, // NULL = fallback for all dates
                    'commission_amount_type' => 'custom field', // Explicitly set as custom field
                    'custom_sales_field_id' => $customFieldId ?? $earliestCustomFieldRecord->custom_sales_field_id,
                    'commission_parentage' => $earliestCustomFieldRecord->commission_parentage ?? 0,
                    'commission_parentag_hiring_locked' => $earliestCustomFieldRecord->commission_parentag_hiring_locked ?? 0,
                    'commission_amount_type_locked' => $earliestCustomFieldRecord->commission_amount_type_locked ?? 0,
                    'commission_structure_type' => $earliestCustomFieldRecord->commission_structure_type,
                    'commission_parentag_type_hiring_locked' => $earliestCustomFieldRecord->commission_parentag_type_hiring_locked ?? 0,
                ]);
            });
        });
    }

    /**
     * Backfill NULL effective_date fallbacks for all existing custom field records.
     *
     * Run this once via artisan tinker or a command to fix existing data:
     * \App\Models\PositionCommission::backfillCustomFieldFallbacks();
     *
     * @return array Summary of what was created
     */
    public static function backfillCustomFieldFallbacks(): array
    {
        $created = 0;
        $skipped = 0;

        // Find all unique position/product combinations with custom field but potentially no NULL fallback
        $combinations = static::where('commission_amount_type', 'custom field')
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
        'self_gen_user',
        'commission_parentage',
        'commission_amount_type',
        'commission_status',
        'commission_parentag_hiring_locked',
        'commission_amount_type_locked',
        'commission_structure_type',
        'commission_parentag_type_hiring_locked',
        'commission_status',
        'tiers_id',
        'tiers_hiring_locked',
        'commission_limit',
        'commission_limit_type',
        'effective_date',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tiersRange(): HasMany
    {
        return $this->hasMany(TiersPositionCommission::class, 'position_commission_id', 'id')->with('tiersSchema');
    }

    /**
     * Get the position associated with this commission
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Positions::class, 'position_id');
    }

    /**
     * Get the custom sales field associated with this position commission
     */
    public function customSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}

// "upfront":[{"product_id":"12","upfront_status":"0",
//     "data":[
//     {"milestone_id":"44","position_id":"22","self_gen_user":"0",
//         "schemas":[
//         {"milestone_schema_trigger_id":"2","core_position_id":"3","upfront_ammount":"1","upfront_ammount_locked":"1","calculated_by":"per kw","calculated_locked":"1","upfront_system":"Fixed","upfront_system_locked":"1","upfront_limit":"1"},
//         {"milestone_schema_trigger_id":"1","core_position_id":"3","upfront_ammount":"1","upfront_ammount_locked":"1","calculated_by":"per kw","calculated_locked":"0","upfront_system":"Fixed","upfront_system_locked":"1","upfront_limit":"1"}
//         ]},
//         {"milestone_id":"2","position_id":"23","self_gen_user":"0",
//         "schemas":[{"milestone_schema_trigger_id":"2","core_position_id":"2","upfront_ammount":"10","upfront_ammount_locked":"1","calculated_by":"per sale","calculated_locked":"1","upfront_system":"Fixed","upfront_system_locked":"0","upfront_limit":"250"},
//         {"milestone_schema_trigger_id":"5","core_position_id":"2","upfront_ammount":"10","upfront_ammount_locked":"1","calculated_by":"per sale","calculated_locked":"1","upfront_system":"Fixed","upfront_system_locked":"0","upfront_limit":"250"}
//         ]},
//         {"milestone_id":"3","position_id":"4","self_gen_user":"1",
//         "schemas":[{"milestone_schema_trigger_id":"2","core_position_id":"3","upfront_ammount":"50","upfront_ammount_locked":"1","calculated_by":"per kw","calculated_locked":"1","upfront_system":"Fixed","upfront_system_locked":"1","upfront_limit":"10"}
//         ]}]
//     }]
