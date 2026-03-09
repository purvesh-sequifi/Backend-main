<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconCommissionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        "pid",
        "user_id",
        "finalize_id",
        "status",
        "start_date",
        "end_date",
        "sent_count",
        "finalize_count",
        "total_amount",
        "paid_amount",
        "payout",
        "payroll_id",
        "payroll_execute_status",
        "pay_period_from",
        "pay_period_to",
        "is_next_payroll",
        "is_mark_paid",
        "is_displayed",
        "ref_id",
        "type",
        "schema_name",
        "schema_type",
        "is_last",
        "move_from_payroll",
        "during",
        "is_ineligible",
        "is_deducted",
        "user_worker_type",
        "pay_frequency",
        "is_onetime_payment",
        "one_time_payment_id"
    ];

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    public function payrollHistory(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollHistory::class, 'id', 'payroll_id');
    }

    public function reconCommissionHistory(): HasOne
    {
        return $this->hasOne(\App\Models\ReconCommissionHistory::class, 'id', 'id');
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
}
