<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegacyApiRowData extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'legacy_api_raw_data';

    protected $fillable = [
        'legacy_data_id',
        'aveyo_hs_id',
        'aveyo_project',
        'pid',
        'page',
        'weekly_sheet_id',
        'homeowner_id',
        'proposal_id',
        'customer_name',
        'customer_address',
        'customer_address_2',
        'customer_city',
        'customer_state',
        'customer_zip',
        'customer_email',
        'customer_phone',
        'setter_id',
        'closer_id',
        'setter_name',
        'closer_name',
        'employee_id',
        'sales_rep_name',
        'sales_rep_email',
        'install_partner',
        'install_partner_id',
        'customer_signoff',
        'm1_date',
        'scheduled_install',
        'install_complete_date',
        'm2_date',
        'date_cancelled',
        'return_sales_date',
        'gross_account_value',
        'cash_amount',
        'loan_amount',
        'kw',
        'dealer_fee_percentage',
        'adders',
        'cancel_fee',
        'adders_description',
        'funding_source',
        'financing_rate',
        'financing_term',
        'product',
        'epc',
        'net_epc',
        'data_source_type',
        'contract_sign_date',
        'source_created_at',
        'source_updated_at',
        'job_status',
        'length_of_agreement',
        'service_schedule',
        'initial_service_cost',
        'auto_pay',
        'card_on_file',
        'subscription_payment',
        'service_completed',
        'last_service_date',
        'bill_status',
        'milestone_trigger',
        'balance_age',
        'ticket_id',
        'appointment_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function setter(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'setter_id')->select('id', 'first_name', 'last_name', 'image');
    }

    public function excelrawdata(): HasOne
    {
        return $this->hasOne(\App\Models\ImportExpord::class, 'pid', 'pid')->latest();
    }
}
