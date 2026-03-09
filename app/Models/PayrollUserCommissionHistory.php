<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollUserCommissionHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_user_commission_histories';

    protected $fillable = [
        'user_id',
        'position_id',
        'pid',
        'amount_type',
        'amount',
        'redline',
        'redline_type',
        'net_epc',
        'kw',
        'date',
        'pay_period_from',
        'pay_period_to',
        'customer_signoff',
        'status',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function userdata(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'status_id');
    }

    public function saledata(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->select('pid', 'customer_name', 'customer_state', 'kw', 'net_epc', 'm1_date', 'm2_date', 'adders');
    }

    public function payroll(): HasOne
    {
        return $this->hasOne(\App\Models\Payroll::class, 'user_id', 'user_id')->select('override');
    }

    public function payrollAdjustment(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustment::class, 'user_id', 'user_id');
    }
}
