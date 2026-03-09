<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleMasterExcluded extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sale_masters_excluded';

    protected $fillable = [
        'user_id',
        'filter_id',
        'sale_master_id',
        'pid',
        'ticket_id',
        'initialStatusText',
        'appointment_id',
        'closer1_id',
        'setter1_id',
        'closer2_id',
        'setter2_id',
        'closer1_name',
        'setter1_name',
        'closer2_name',
        'setter2_name',
        'prospect_id',
        'panel_type',
        'panel_id',
        'weekly_sheet_id',
        'install_partner',
        'install_partner_id',
        'customer_name',
        'customer_address',
        'customer_address_2',
        'customer_state',
        'customer_zip',
        'customer_longitude',
        'customer_latitude',
        'customer_city',
        'location_code',
        'customer_email',
        'customer_phone',
        'homeowner_id',
        'proposal_id',
        'sales_rep_name',
        'employee_id',
        'sales_rep_email',
        'kw',
        'balance_age',
        'date_cancelled',
        'customer_signoff',
        'm1_date',
        'm2_date',
        'product',
        'product_id',
        'product_code',
        'sale_product_name',
        'is_exempted',
        'total_commission_amount',
        'total_override_amount',
        'milestone_trigger',
        'gross_account_value',
        'epc',
        'net_epc',
        'dealer_fee_percentage',
        'dealer_fee_amount',
        'adders',
        'adders_description',
        'state_id',
        'm1_amount',
        'total_amount_for_acct',
        'prev_amount_paid',
        'total_due',
        'm2_amount',
        'prev_deducted_amount',
        'cancel_fee',
        'cancel_deduction',
        'lead_cost_amount',
        'adv_pay_back_amount',
        'total_amount_in_period',
        'funding_source',
        'financing_rate',
        'financing_term',
        'scheduled_install',
        'install_complete_date',
        'return_sales_date',
        'cash_amount',
        'loan_amount',
        'length_of_agreement',
        'service_schedule',
        'initial_service_cost',
        'auto_pay',
        'card_on_file',
        'subscription_payment',
        'service_completed',
        'last_service_date',
        'last_date_pd',
        'initial_service_date',
        'bill_status',
        'sales_type',
        'm1_source_type',
        'job_status',
        'trigger_date',
        'sale_item_status',
        'total_commission',
        'projected_commission',
        'total_override',
        'data_source_type',
        'redline',
        'projected_override',
        'action_item_status',
        'import_status_reason',
        'import_status_description',
    ];

    protected $casts = [
        'customer_signoff' => 'date',
        'm1_date' => 'date',
        'm2_date' => 'date',
        'install_complete_date' => 'date',
        'last_service_date' => 'date',
        'last_date_pd' => 'date',
        'is_exempted' => 'boolean',
        'milestone_trigger' => 'boolean',
        'sale_item_status' => 'boolean',
        'projected_override' => 'boolean',
        'action_item_status' => 'boolean',
        'total_commission_amount' => 'decimal:2',
        'total_override_amount' => 'decimal:2',
        'gross_account_value' => 'decimal:2',
        'epc' => 'float',
        'net_epc' => 'float',
        'cash_amount' => 'decimal:3',
        'loan_amount' => 'decimal:2',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Relationship with the weekly sheet
     */
    public function weeklySheet(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LegacyWeeklySheet::class, 'weekly_sheet_id');
    }

    /**
     * Relationship with the user (closer1)
     */
    public function closer1(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closer1_id');
    }

    /**
     * Relationship with the user (setter1)
     */
    public function setter1(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'setter1_id');
    }

    /**
     * Relationship with the user (closer2)
     */
    public function closer2(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closer2_id');
    }

    /**
     * Relationship with the user (setter2)
     */
    public function setter2(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'setter2_id');
    }

    /**
     * Relationship with the product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Products::class, 'product_id');
    }
}
