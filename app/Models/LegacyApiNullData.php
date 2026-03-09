<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LegacyApiNullData extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'legacy_api_data_null';

    protected $fillable = [
        'legacy_data_id',
        'aveyo_hs_id',
        'aveyo_project',
        'pid',
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
        'closer_id',
        'setter_name',
        'closer_name',
        'employee_id',
        'sales_rep_name',
        'sales_rep_email',
        'sales_setter_email',
        'sales_setter_name',
        'install_partner',
        'install_partner_id',
        'customer_signoff',
        'm1_date',
        'scheduled_install',
        'install_complete_date',
        'm2_date',
        'date_cancelled',
        'inactive_date',
        'return_sales_date',
        'gross_account_value',
        'cash_amount',
        'loan_amount',
        'kw',
        'dealer_fee_percentage',
        'dealer_fee_dollar',
        'dealer_fee_amount',
        'shows',
        'redline',
        'total_for_acct',
        'prev_paid',
        'last_date_pd',
        'm1_this_week',
        'install_m2_this_week',
        'prev_deducted',
        'cancel_deduction',
        'lead_cost',
        'adv_pay_back_amount',
        'total_in_period',
        'adders',
        'cancel_fee',
        'adders_description',
        'funding_source',
        'financing_rate',
        'financing_term',
        'product',
        'product_id',
        'product_code',
        'sale_product_name',
        'epc',
        'net_epc',
        'type',
        'status',
        'action_status',
        'email_status',
        'data_source_type',
        'closedpayroll_type',
        'contract_sign_date',
        'sales_type',
        'job_status',
        'source_created_at',
        'source_updated_at',
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
        'initial_service_date',
        'trigger_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function salesMasterProcess(): HasOne
    {
        return $this->hasOne(\App\Models\SaleMasterProcess::class, 'pid', 'pid')->with('salesDetail', 'setter1Detail', 'setter2Detail', 'closer1Detail', 'closer2Detail');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'email', 'sales_rep_email');
    }

    public function userAdditionalEmail(): HasOne
    {
        return $this->hasOne(\App\Models\UsersAdditionalEmail::class, 'email', 'sales_rep_email');
    }

    // public function userDetailByName()
    // {
    //     return $this->hasOne('App\Models\User','email', 'sales_rep_name');
    // }
}
