<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconciliationFinalizeHistory extends Model
{
    use HasFactory;

    protected $table = 'reconciliation_finalize_history';

    protected $fillable = [
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
        "finalize_count",
        "deductions",
        "payroll_execute_status",
        "percentage_pay_amount",
        "is_upfront",
        "is_onetime_payment",
        "one_time_payment_id",
        'user_worker_type',
        'pay_frequency'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function saleData(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->select('pid', 'customer_name');
    }

    public function position(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function office(): HasOne
    {
        return $this->hasOne(\App\Models\Locations::class, 'id', 'office_id');
    }

    public function payrollcommon(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
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
}
