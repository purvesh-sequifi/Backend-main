<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use App\Services\SalesCalculationContext;
use App\Services\SalesCustomFieldCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class PositionOverride extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'position_commission_overrides';

    // OFFICE OVERRIDE ID FROM overrides__types TABLE
    const OFFICE_OVERRIDE_TYPE_ID = '3';

    /**
     * Boot the model and register the retrieved event for custom field conversion.
     *
     * This implements the "Trick Subroutine" approach for overrides:
     * - When an override is fetched with type = 'custom field'
     * - AND the company has the feature enabled
     * - AND a sale context is set
     * - Auto-convert to 'per sale' with calculated amount
     *
     * Note: Overrides have multiple custom field types (direct, indirect, office).
     * The conversion uses the appropriate custom field ID based on override_id.
     *
     * This ensures ZERO modifications to SubroutineTrait.
     */
    protected static function booted(): void
    {
        static::retrieved(function (PositionOverride $override) {
            // EARLY EXIT: Check type FIRST before any context checks
            // This prevents unnecessary context/feature flag checks for most overrides
            if ($override->type !== 'custom field') {
                return;
            }

            // Determine which custom field ID to use based on override type
            // Override types: 1 = direct, 2 = indirect, 3 = office
            $customFieldId = null;
            switch ($override->override_id) {
                case '1': // Direct override
                    $customFieldId = $override->direct_custom_sales_field_id;
                    break;
                case '2': // Indirect override
                    $customFieldId = $override->indirect_custom_sales_field_id;
                    break;
                case '3': // Office override
                    $customFieldId = $override->office_custom_sales_field_id;
                    break;
                default:
                    // For any other type, try direct first, then indirect, then office
                    $customFieldId = $override->direct_custom_sales_field_id
                        ?? $override->indirect_custom_sales_field_id
                        ?? $override->office_custom_sales_field_id;
            }

            // Must have a custom field ID
            if (empty($customFieldId)) {
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
                $override->type = 'per sale';
                $override->override_ammount = 0;
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
                $customFieldId
            ) ?? 0;

            $configuredAmount = $override->override_ammount ?? 0;
            $calculatedAmount = $configuredAmount * $customFieldValue;

            // Store metadata in model's attributes array (PHP 8.2 compatible)
            $override->setAttribute('_original_type', 'custom field');
            $override->setAttribute('_custom_field_value', $customFieldValue);
            $override->setAttribute('_custom_field_id', $customFieldId);
            $override->setAttribute('_configured_amount', $configuredAmount);

            // Convert to 'per sale' with calculated amount (not persisted to DB)
            $override->type = 'per sale';
            $override->override_ammount = $calculatedAmount;
        });

        // Auto-create NULL effective_date fallback for backdata sales when saving custom field records
        // This follows the same pattern as existing non-custom-field records in SubroutineTrait
        static::saved(function (PositionOverride $record) {
            // Only for custom field type with a specific effective_date
            if ($record->type === 'custom field'
                && $record->effective_date !== null) {

                // Get the custom field ID based on override type
                $customFieldId = match ($record->override_id) {
                    '1' => $record->direct_custom_sales_field_id,
                    '2' => $record->indirect_custom_sales_field_id,
                    '3' => $record->office_custom_sales_field_id,
                    default => $record->direct_custom_sales_field_id
                        ?? $record->indirect_custom_sales_field_id
                        ?? $record->office_custom_sales_field_id,
                };

                if ($customFieldId) {
                    static::ensureBackdataFallback(
                        $record->position_id,
                        $record->product_id,
                        $record->override_id,
                        $customFieldId
                    );
                }
            }
        });
    }

    /**
     * Ensure a fallback (NULL effective_date) record exists for backdata sales.
     *
     * This follows the same pattern as existing non-custom-field records.
     * SubroutineTrait checks: effective_date <= sale_date, then falls back to NULL effective_date.
     * Without a NULL fallback, sales dated before the earliest effective_date get no override.
     *
     * @param int $positionId The position ID
     * @param int $productId The product ID
     * @param string $overrideId The override type (1=direct, 2=indirect, 3=office)
     * @param int|null $customFieldId The custom sales field ID
     * @return void
     */
    public static function ensureBackdataFallback(int $positionId, int $productId, string $overrideId, ?int $customFieldId): void
    {
        // Use database transaction with locking to prevent race conditions
        // where two simultaneous requests could both pass the exists check
        DB::transaction(function () use ($positionId, $productId, $overrideId, $customFieldId) {
            // Check if a NULL effective_date custom field record already exists for this position/product/override_type
            // Use lockForUpdate to prevent race conditions
            $fallbackExists = static::where('position_id', $positionId)
                ->where('product_id', $productId)
                ->where('override_id', $overrideId)
                ->whereNull('effective_date')
                ->where('type', 'custom field')
                ->lockForUpdate()
                ->exists();

            if ($fallbackExists) {
                return; // Already has custom field fallback
            }

            // Get the earliest CUSTOM FIELD record to copy settings from (for backdata)
            $earliestCustomFieldRecord = static::where('position_id', $positionId)
                ->where('product_id', $productId)
                ->where('override_id', $overrideId)
                ->where('type', 'custom field')
                ->whereNotNull('effective_date')
                ->orderBy('effective_date', 'asc')
                ->first();

            if (!$earliestCustomFieldRecord) {
                return; // No custom field records to copy from
            }

            // Create NULL effective_date fallback (for sales before earliest date)
            // Use withoutEvents to prevent recursion
            static::withoutEvents(function () use ($positionId, $productId, $overrideId, $customFieldId, $earliestCustomFieldRecord) {
                static::create([
                    'position_id' => $positionId,
                    'product_id' => $productId,
                    'override_id' => $overrideId,
                    'status' => $earliestCustomFieldRecord->status,
                    'effective_date' => null, // NULL = fallback for all dates
                    'type' => 'custom field', // Explicitly set as custom field
                    'override_ammount' => $earliestCustomFieldRecord->override_ammount ?? 0,
                    'override_ammount_locked' => $earliestCustomFieldRecord->override_ammount_locked ?? 0,
                    'override_type_locked' => $earliestCustomFieldRecord->override_type_locked ?? 0,
                    'direct_custom_sales_field_id' => $overrideId === '1' ? $customFieldId : $earliestCustomFieldRecord->direct_custom_sales_field_id,
                    'indirect_custom_sales_field_id' => $overrideId === '2' ? $customFieldId : $earliestCustomFieldRecord->indirect_custom_sales_field_id,
                    'office_custom_sales_field_id' => $overrideId === '3' ? $customFieldId : $earliestCustomFieldRecord->office_custom_sales_field_id,
                ]);
            });
        });
    }

    /**
     * Backfill NULL effective_date fallbacks for all existing custom field override records.
     *
     * Run this once via artisan tinker or a command to fix existing data:
     * \App\Models\PositionOverride::backfillCustomFieldFallbacks();
     *
     * @return array Summary of what was created
     */
    public static function backfillCustomFieldFallbacks(): array
    {
        $created = 0;
        $skipped = 0;

        // Find all unique position/product/override_id combinations with custom field but potentially no NULL fallback
        $combinations = static::where('type', 'custom field')
            ->whereNotNull('effective_date')
            ->select('position_id', 'product_id', 'override_id', 'direct_custom_sales_field_id', 'indirect_custom_sales_field_id', 'office_custom_sales_field_id')
            ->distinct()
            ->get();

        foreach ($combinations as $combo) {
            // Determine custom field ID based on override type
            $customFieldId = match ($combo->override_id) {
                '1' => $combo->direct_custom_sales_field_id,
                '2' => $combo->indirect_custom_sales_field_id,
                '3' => $combo->office_custom_sales_field_id,
                default => $combo->direct_custom_sales_field_id
                    ?? $combo->indirect_custom_sales_field_id
                    ?? $combo->office_custom_sales_field_id,
            };

            if (!$customFieldId) {
                $skipped++;
                continue;
            }

            // Check if fallback already exists
            $fallbackExists = static::where('position_id', $combo->position_id)
                ->where('product_id', $combo->product_id)
                ->where('override_id', $combo->override_id)
                ->whereNull('effective_date')
                ->where('type', 'custom field')
                ->exists();

            if ($fallbackExists) {
                $skipped++;
                continue;
            }

            static::ensureBackdataFallback(
                $combo->position_id,
                $combo->product_id,
                $combo->override_id,
                $customFieldId
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
        'product_id',
        'override_id',
        'settlement_id',
        'override_ammount',
        'override_ammount_locked',
        'type',
        'override_type_locked',
        'status',
        'tiers_id',
        'tiers_hiring_locked',
        'override_limit',
        'override_limit_type',
        'effective_date',
        'direct_custom_sales_field_id',   // Custom Sales Field feature
        'indirect_custom_sales_field_id', // Custom Sales Field feature
        'office_custom_sales_field_id',   // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function overridesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\OverridesType::class, 'id', 'override_id')->select('id', 'overrides_type');
    }

    public function overridessattlement(): HasOne
    {
        return $this->hasOne(\App\Models\PositionOverrideSettlement::class, 'id', 'settlement_id')->select('id', 'sattlement_type');
    }

    public function tiersRange(): HasMany
    {
        return $this->hasMany(TiersPositionOverrides::class, 'position_overrides_id', 'id');
    }

    /**
     * Get the direct custom sales field for this override
     */
    public function directCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'direct_custom_sales_field_id');
    }

    /**
     * Get the indirect custom sales field for this override
     */
    public function indirectCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'indirect_custom_sales_field_id');
    }

    /**
     * Get the office custom sales field for this override
     */
    public function officeCustomSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'office_custom_sales_field_id');
    }
}
