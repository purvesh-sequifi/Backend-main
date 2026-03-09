<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconOverrideHistory extends Model
{
    use HasFactory;

    protected $table = 'recon_override_history';

    const USER_CLASS = \App\Models\User::class;

    protected $fillable = [
        'user_id',
        'finalize_id',
        'pid',
        'start_date',
        'end_date',
        'customer_name',
        'overrider',
        'type',
        'kw',
        'override_amount',
        'total_amount',
        'paid',
        'percentage',
        'status',
        'sent_count',
        "payroll_execute_status",
        "pay_period_from",
        "pay_period_to",
        "payroll_id",
        "is_next_payroll",
        "is_mark_paid",
        "is_displayed",
        "override_id",
        "finalize_count",
        "ref_id",
        "move_from_payroll",
        "during",
        "overrides_settlement_type",
        "is_ineligible",
        "user_worker_type",
        "pay_frequency",
        "is_onetime_payment",
        "one_time_payment_id"
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function userpayrolloverride(): HasOne
    {
        return $this->hasOne(self::USER_CLASS, 'id', 'user_id');
    }

    public function userData(): HasOne
    {
        return $this->hasOne(self::USER_CLASS, 'id', 'user_id');
    }

    public function overrideOverData(): HasOne
    {
        return $this->hasOne(self::USER_CLASS, 'id', 'overrider');
    }

    public function reconOverrideHistoryData(): HasOne
    {
        return $this->hasOne(\App\Models\ReconOverrideHistory::class, 'id', 'id');
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
