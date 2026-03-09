<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegacyApiRawDataHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'legacy_api_raw_data_histories';

    protected $fillable = [
        'legacy_id',
        'pid',
        'customer_id',
        'template_id',
        'initialAppointmentID',
        'soldBy',
        'soldBy2',
        'initialStatusText',
        'weekly_sheet_id',
        'homeowner_id',
        'proposal_id',
        'customer_name',
        'customer_address',
        'customer_address_2',
        'customer_city',
        'customer_state',
        'location_code',
        'customer_zip',
        'customer_email',
        'customer_phone',
        'setter_id',
        'sales_setter_name',
        'employee_id',
        'sales_rep_name',
        'sales_rep_email',
        'install_partner',
        'install_partner_id',
        'customer_signoff',
        'm1_date',
        'm2_date',
        'scheduled_install',
        'install_complete_date',
        'date_cancelled',
        'return_sales_date',
        'gross_account_value',
        'cash_amount',
        'loan_amount',
        'kw',
        'dealer_fee_percentage',
        'dealer_fee_amount',
        'adders',
        'cancel_fee',
        'adders_description',
        'redline',
        'total_amount_for_acct',
        'prev_amount_paid',
        'last_date_pd',
        'm1_amount',
        'm2_amount',
        'prev_deducted_amount',
        'cancel_deduction',
        'lead_cost_amount',
        'adv_pay_back_amount',
        'total_amount_in_period',
        'funding_source',
        'financing_rate',
        'financing_term',
        'product',
        'product_id',
        'product_code',
        'sale_product_name',
        'epc',
        'net_epc',
        'closer1_id',
        'closer2_id',
        'setter1_id',
        'setter2_id',
        'closer1_m1',
        'closer2_m1',
        'setter1_m1',
        'setter2_m1',
        'closer1_m2',
        'closer2_m2',
        'setter1_m2',
        'setter2_m2',
        'closer1_commission',
        'closer2_commission',
        'setter1_commission',
        'setter2_commission',
        'closer1_m1_paid_status',
        'closer2_m1_paid_status',
        'setter1_m1_paid_status',
        'setter2_m1_paid_status',
        'closer1_m2_paid_status',
        'closer2_m2_paid_status',
        'setter1_m2_paid_status',
        'setter2_m2_paid_status',
        'closer1_m1_paid_date',
        'closer2_m1_paid_date',
        'setter1_m1_paid_date',
        'setter2_m1_paid_date',
        'closer1_m2_paid_date',
        'closer2_m2_paid_date',
        'setter1_m2_paid_date',
        'setter2_m2_paid_date',
        'mark_account_status_id',
        'pid_status',
        'source_created_at',
        'source_updated_at',
        'data_source_type',
        'light_validation',
        'pay_period_from',
        'pay_period_to',
        'import_to_sales', // 0 = Pending, 1 = Success, 2 = Error, 3 = Manually Marked When This Functionality Implemented
        'import_status_reason',
        'import_status_description',
        'excel_import_id',
        'contract_sign_date',
        'job_status',
        'created_at',
        'length_of_agreement',
        'service_schedule',
        'initial_service_cost',
        'auto_pay',
        'card_on_file',
        'subscription_payment',
        'service_completed',
        'last_service_date',
        'bill_status',
        'trigger_date',
        'balance_age',
        'ticket_id',
        'appointment_id',
        'initial_service_date',
        'customer_payment_json',
        // Sales Import Audit Trail fields
        'closer1_flexiable_id',
        'closer2_flexiable_id',
        'setter1_flexiable_id',
        'setter2_flexiable_id',
        'mapped_fields',
        'custom_field_values'
    ];
    
    protected $casts = [
        'mapped_fields' => 'array',
        'custom_field_values' => 'array',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function userDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'email', 'sales_rep_email');
    }

    /**
     * Scope a query to only include records where import_to_sales is not zero.
     */
    public function scopeImportable(Builder $query): Builder
    {
        return $query->where('import_to_sales', '!=', 0);
    }

    /**
     * 🎯 AUDIT TRAIL: Scope to find records with specific flexible ID values
     */
    public function scopeWithFlexibleId($query, $flexibleIdValue)
    {
        return $query->where(function ($q) use ($flexibleIdValue) {
            $q->where('closer1_flexiable_id', $flexibleIdValue)
                ->orWhere('closer2_flexiable_id', $flexibleIdValue)
                ->orWhere('setter1_flexiable_id', $flexibleIdValue)
                ->orWhere('setter2_flexiable_id', $flexibleIdValue);
        });
    }

    /**
     * 🎯 AUDIT TRAIL: Get all flexible ID values used in this record
     */
    public function getUsedFlexibleIds(): array
    {
        $flexibleIds = [];

        if (! empty($this->closer1_flexiable_id)) {
            $flexibleIds['closer1'] = $this->closer1_flexiable_id;
        }
        if (! empty($this->closer2_flexiable_id)) {
            $flexibleIds['closer2'] = $this->closer2_flexiable_id;
        }
        if (! empty($this->setter1_flexiable_id)) {
            $flexibleIds['setter1'] = $this->setter1_flexiable_id;
        }
        if (! empty($this->setter2_flexiable_id)) {
            $flexibleIds['setter2'] = $this->setter2_flexiable_id;
        }

        return $flexibleIds;
    }

    /**
     * 🎯 AUDIT TRAIL: Check if this record used any flexible IDs during import
     */
    public function hasFlexibleIdAuditData(): bool
    {
        return ! empty($this->closer1_flexiable_id) ||
               ! empty($this->closer2_flexiable_id) ||
               ! empty($this->setter1_flexiable_id) ||
               ! empty($this->setter2_flexiable_id);
    }

    /**
     * 🎯 AUDIT TRAIL: Get audit trail summary for this record
     */
    public function getAuditTrailSummary(): array
    {
        return [
            'pid' => $this->pid,
            'customer_name' => $this->customer_name,
            'import_to_sales' => $this->import_to_sales,
            'data_source_type' => $this->data_source_type,
            'template_id' => $this->template_id,
            'flexible_ids_used' => $this->getUsedFlexibleIds(),
            'has_flexible_id_data' => $this->hasFlexibleIdAuditData(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            // Current user mappings for comparison
            'current_mappings' => [
                'closer1_id' => $this->closer1_id,
                'closer2_id' => $this->closer2_id,
                'setter1_id' => $this->setter1_id,
                'setter2_id' => $this->setter2_id,
            ],
        ];
    }

    /**
     * 🎯 AUDIT TRAIL: Find records that used a specific flexible ID but failed to import
     */
    public static function findFailedImportsWithFlexibleId($flexibleIdValue)
    {
        return self::withFlexibleId($flexibleIdValue)
            ->where('import_to_sales', 0) // Failed imports
            ->get();
    }

    /**
     * 🎯 AUDIT TRAIL: Get statistics about flexible ID usage
     */
    public static function getFlexibleIdUsageStats($dateFrom = null, $dateTo = null)
    {
        $query = self::query();

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $records = $query->get();

        $stats = [
            'total_records' => $records->count(),
            'records_with_flexible_ids' => $records->filter(fn ($r) => $r->hasFlexibleIdAuditData())->count(),
            'flexible_id_usage' => [
                'closer1' => $records->whereNotNull('closer1_flexiable_id')->count(),
                'closer2' => $records->whereNotNull('closer2_flexiable_id')->count(),
                'setter1' => $records->whereNotNull('setter1_flexiable_id')->count(),
                'setter2' => $records->whereNotNull('setter2_flexiable_id')->count(),
            ],
            'unique_flexible_ids' => [
                'closer1' => $records->pluck('closer1_flexiable_id')->filter()->unique()->count(),
                'closer2' => $records->pluck('closer2_flexiable_id')->filter()->unique()->count(),
                'setter1' => $records->pluck('setter1_flexiable_id')->filter()->unique()->count(),
                'setter2' => $records->pluck('setter2_flexiable_id')->filter()->unique()->count(),
            ],
            'import_success_rate_with_flexible_ids' => [
                'total_with_flexible_ids' => $records->filter(fn ($r) => $r->hasFlexibleIdAuditData())->count(),
                'successful_with_flexible_ids' => $records->filter(fn ($r) => $r->hasFlexibleIdAuditData() && $r->import_to_sales == 1)->count(),
            ],
        ];

        // Calculate success rate percentage
        if ($stats['import_success_rate_with_flexible_ids']['total_with_flexible_ids'] > 0) {
            $stats['import_success_rate_with_flexible_ids']['success_percentage'] = round(
                ($stats['import_success_rate_with_flexible_ids']['successful_with_flexible_ids'] /
                 $stats['import_success_rate_with_flexible_ids']['total_with_flexible_ids']) * 100, 2
            );
        } else {
            $stats['import_success_rate_with_flexible_ids']['success_percentage'] = 0;
        }

        return $stats;
    }
}
