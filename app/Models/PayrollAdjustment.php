<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollAdjustment extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'payroll_adjustments';

    protected $fillable = [
        'payroll_id',
        'user_id',
        'commission_type',
        'commission_amount',
        'overrides_type',
        'overrides_amount',
        'adjustments_type',
        'adjustments_amount',
        'reimbursements_type',
        'reimbursements_amount',
        'deductions_type',
        'deductions_amount',
        'clawbacks_type',
        'clawbacks_amount',
        'reconciliations_type',
        'reconciliations_amount',
        'hourlysalary_type',
        'hourlysalary_amount',
        'overtime_type',
        'overtime_amount',
        'comment',
        'pay_period_from',
        'pay_period_to',
        'is_mark_paid',
        'is_next_payroll',
        'status',
        'ref_id',
        'user_worker_type',
        'pay_frequency',
        "is_move_to_recon",
        'is_onetime_payment',
        'one_time_payment_id',
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(\App\Models\PayrollAdjustmentDetail::class, 'payroll_id', 'payroll_id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne('App\Models\user', 'id', 'user_id');
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
    
}
