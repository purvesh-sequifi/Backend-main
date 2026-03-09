<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollDeductions extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'user_id',
        'cost_center_id',
        'amount',
        'limit',
        'total',
        'outstanding',
        'subtotal',
        'pay_period_from',
        'pay_period_to',
        'status',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'ref_id',
        'user_worker_type',
        'pay_frequency',
        "is_move_to_recon",
        "is_move_to_recon_paid",
        'is_onetime_payment',
        'one_time_payment_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_center_id');
    }

    public function payrolldetails(): HasOne
    {
        return $this->hasOne(\App\Models\Payroll::class, 'id', 'payroll_id');
    }

    public function payrollcommon(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
    }

    public function userdata(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'position_id', 'sub_position_id', 'stop_payroll');
    }

    /**
     * Scope to apply pay period filter based on frequency type
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param array $additionalWhere Additional where conditions to apply
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApplyFrequencyFilter($query, $param = [], $additionalWhere = [])
    {
        // Apply pay period filter based on frequency
        $query->when($param['pay_frequency'] == FrequencyType::DAILY_PAY_ID, function ($query) use ($param) {
            $query->whereBetween('pay_period_from', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereBetween('pay_period_to', [$param['pay_period_from'], $param['pay_period_to']]);
        }, function ($query) use ($param) {
            $query->where([
                'pay_period_from' => $param['pay_period_from'],
                'pay_period_to' => $param['pay_period_to']
            ]);
        });

        $query->where(['user_worker_type' => $param['worker_type'], 'pay_frequency' => $param['pay_frequency']]);

        // Apply additional where conditions if provided
        if (!empty($additionalWhere)) {
            $query->where($additionalWhere);
        }

        return $query;
    }

    public function payrollCostCenter()
    {
        return $this->hasOne(CostCenter::class,'id','cost_center_id');
    }

    public function payrollReference()
    {
        return $this->hasOne(PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
    }

    public function payrollUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
