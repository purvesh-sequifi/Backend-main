<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollUserOverrideHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_user_override_histories';

    protected $fillable = [
        'user_id',
        'type',
        'sale_user_id',
        'pid',
        'net_epc',
        'kw',
        'amount',
        'overrides_amount',
        'copmment',
        'overrides_type',
        'adjustment_amount',
        'calculated_redline',
        'pay_period_from',
        'pay_period_to',
        'overrides_settlement_type',
        'customer_signoff',
        'status',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'office_id',
        'created_at',
    ];

    protected $hidden = [
        // 'created_at',
        'updated_at',
    ];

    public function userInfo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id');
    }

    public function userdata(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->with('recruiter')->where('dismiss', 0);
    }

    public function userpayrolloverride(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id'); // ->with('recruiter');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function payrollAdjustments(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustment::class, 'user_id', 'user_id');
    }
}
