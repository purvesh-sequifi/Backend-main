<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImportExpord extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'legacy_excel_raw_data';

    protected $fillable = [
        'ct',
        'weekly_sheet_id',
        'affiliate',
        'pid',
        'install_partner',
        'customer_name',
        'sales_rep_name',
        'sales_rep_email',
        'sales_setter_email',
        'kw',
        'cancel_date',
        'approved_date',
        'm1_date',
        'm2_date',
        'state',
        'product',
        'gross_account_value',
        'epc',
        'net_epc',
        'dealer_fee_percentage',
        'dealer_fee_dollar',
        'show',
        'redline',
        'total_for_acct',
        'prev_paid',
        'last_date_pd',
        'm1_this_week',
        'install_m2_this_week',
        'prev_deducted',
        'cancel_fee',
        'cancel_deduction',
        'lead_cost',
        'adv_pay_back_amount',
        'total_in_period',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function userDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'email', 'sales_rep_email');
    }

    public function salesMasterProcess(): HasOne
    {
        return $this->hasOne(\App\Models\SaleMasterProcess::class, 'sale_master_id', 'id')->with('closer1Detail', 'closer2Detail', 'setter1Detail', 'setter2Detail', 'status1');
    }

    public function apiNullData(): HasOne
    {
        return $this->hasOne(\App\Models\LegacyApiNullData::class, 'pid', 'pid');
    }

    // public function userDetail()
    // {
    //     return $this->hasOne('App\Models\User','email', 'sales_rep_email');
    // }

    public function closerDetail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'email', 'sales_rep_email')->select('id', 'first_name', 'last_name', 'image');
    }

    public function clawbackAmount(): HasMany
    {
        return $this->hasMany(\App\Models\ClawbackSettlement::class, 'pid', 'pid')->select('pid', 'clawback_amount');
    }
}
