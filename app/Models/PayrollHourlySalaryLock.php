<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollHourlySalaryLock extends Model
{
    use HasFactory;

    protected $table = 'payroll_hourly_salary_lock';

    protected $fillable = [
        'id',
        'payroll_id',
        'user_id',
        'position_id',
        'date',
        'hourly_rate',
        'salary',
        'regular_hours',
        'total',
        'pay_period_from',
        'pay_period_to',
        'status',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'is_move_to_recon',
        'user_worker_type',
        'pay_frequency',
        'is_onetime_payment',
        'one_time_payment_id',
        'ref_id'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function userdata(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_manager', 'is_super_admin', 'status_id', 'stop_payroll', 'worker_type');
    }

    public function oneTimePaymentDetail(): HasOne
    {
        return $this->hasOne(\App\Models\OneTimePayments::class, 'id', 'one_time_payment_id');
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
                ->whereBetween('pay_period_to', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereColumn('pay_period_from', 'pay_period_to');
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

    public function payrollReference()
    {
        return $this->hasOne(PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
    }

    public function payrollUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    
}
