<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconciliationFinalizeHistoryLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'pid',
        'finalize_id',
        'office_id',
        'position_id',
        'start_date',
        'end_date',
        'executed_on',
        'commission',
        'override',
        'paid_commission',
        'paid_override',
        'clawback',
        'adjustments',
        'remaining',
        'gross_amount',
        'payout',
        'net_amount',
        'type',
        'status',
        'pay_period_from',
        'pay_period_to',
        'payroll_id',
        'sent_count',
        "is_mark_paid",
        "is_next_payroll",
        "is_stop_payroll",
        "ref_id",
        "move_from_payroll_row_id",
        "move_from_payroll_flag",
        "deductions",
        "payroll_execute_status",
        "finalize_count",
        "is_onetime_payment",
        "one_time_payment_id",
        'user_worker_type',
        'pay_frequency'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

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
}
