<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalesMaster extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sale_masters';

    protected $fillable = [
        'pid',
        'weekly_sheet_id',
        'initialStatusText',
        'install_partner',
        'install_partner_id',
        'customer_name',
        'customer_address',
        'customer_address_2',
        'state_id',
        'customer_city',
        'customer_state',
        'location_code',
        'customer_zip',
        'customer_email',
        'customer_phone',
        'homeowner_id',
        'proposal_id',
        'sales_rep_name',
        'employee_id',
        'sales_rep_email',
        'closer1_id',
        'closer2_id',
        'setter1_id',
        'setter2_id',
        'closer1_name',
        'closer2_name',
        'setter1_name',
        'setter2_name',
        'kw',
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
        'gross_account_value',
        'epc',
        'net_epc',
        'dealer_fee_percentage',
        'dealer_fee_amount',
        'adders',
        'adders_description',
        'redline',
        'total_amount_for_acct',
        'prev_amount_paid',
        'last_date_pd',
        'm1_amount',
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
        'data_source_type',
        'sales_type',
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
        'total_commission',
        'projected_commission',
        'total_override',
        'projected_override',
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
        return $this->hasOne(\App\Models\SaleMasterProcess::class, 'sale_master_id', 'id')->with('closer1Detail', 'closer2Detail', 'setter1Detail', 'setter2Detail', 'status', 'status1');
    }

    public function salesMasterProcessInfo(): HasOne
    {
        return $this->hasOne(SaleMasterProcess::class, 'sale_master_id', 'id');
    }

    public function crmsaleinfo(): HasOne
    {
        return $this->hasOne(\App\Models\Crmsaleinfo::class, 'pid', 'pid')->with('bucketbyjob');
    }

    public function sales_master_process(): HasOne
    {
        return $this->hasOne(\App\Models\SaleMasterProcess::class, 'sale_master_id', 'id');
    }

    public function getMone(): HasOne
    {
        return $this->hasOne(\App\Models\SaleMasterProcess::class, 'sale_master_id', 'id');
    }

    public function getMtwo(): HasOne
    {
        return $this->hasOne(\App\Models\SaleMasterProcess::class, 'sale_master_id', 'id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'email', 'sales_rep_email');
    }

    public function closerDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'email', 'sales_rep_email')->select('id', 'first_name', 'last_name', 'image');
    }

    public function clawbackAmount(): HasMany
    {
        return $this->hasMany(\App\Models\ClawbackSettlement::class, 'pid', 'pid')->select('pid', 'clawback_amount');
    }

    public function userCommission(): HasMany
    {
        return $this->hasMany(\App\Models\UserCommission::class, 'pid', 'pid')->select('pid', 'amount_type', 'status')->where('status', 1);
    }

    public function override(): HasMany
    {
        return $this->hasMany(UserOverrides::class, 'pid', 'pid');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(\App\Models\UserCommission::class, 'pid', 'pid');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function legacyAPINull(): HasOne
    {
        return $this->hasOne(\App\Models\LegacyApiNullData::class, 'pid', 'pid');
    }

    public function productdata(): HasOne
    {
        return $this->hasOne(\App\Models\Products::class, 'id', 'product')->where('status', 1);
    }

    public function productInfo(): HasOne
    {
        return $this->hasOne(ProductCode::class, 'product_id', 'product_id');
    }

    public function salesProductMaster(): HasMany
    {
        return $this->hasMany(SaleProductMaster::class, 'pid', 'pid');
    }

    public function salesProductMasterDetails(): HasMany
    {
        return $this->hasMany(SaleProductMaster::class, 'pid', 'pid');
    }

    public function lastMilestone(): HasMany
    {
        return $this->hasMany(SaleProductMaster::class, 'pid', 'pid');
    }

    public function closer1Detail(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'closer1_id')->select('id', 'first_name', 'last_name', 'email', 'image', 'office_id', 'sub_position_id', 'terminate', 'dismiss', 'contract_ended', 'stop_payroll');
    }

    public function setter1Detail(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'setter1_id')->select('id', 'first_name', 'last_name', 'email', 'office_id', 'sub_position_id', 'terminate', 'dismiss', 'contract_ended', 'stop_payroll');
    }

    public function closer2Detail(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'closer2_id')->select('id', 'first_name', 'last_name', 'email', 'office_id', 'sub_position_id', 'terminate', 'dismiss', 'contract_ended', 'stop_payroll');
    }

    public function setter2Detail(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'setter2_id')->select('id', 'first_name', 'last_name', 'email', 'office_id', 'sub_position_id', 'terminate', 'dismiss', 'contract_ended', 'stop_payroll');
    }

    public function customerPayments(): HasOne
    {
        return $this->hasOne(CustomerPayment::class, 'pid', 'pid')->select('customer_payment_json', 'pid');
    }

    public function externalSaleProductMaster(): HasMany
    {
        return $this->hasMany(ExternalSaleProductMaster::class, 'pid', 'pid');
    }

    public function externalSaleWorker(): HasMany
    {
        return $this->hasMany(ExternalSaleWorker::class, 'pid', 'pid');
    }

    public function externalSaleProductMasterDetails(): HasMany
    {
        return $this->hasMany(ExternalSaleProductMaster::class, 'pid', 'pid');
    }
}
