<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollAdjustmentDetailLock extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'payroll_adjustment_details_lock';

    protected $fillable = [
        'id',
        'payroll_id',
        'user_id',
        'pid',
        'sale_user_id',
        'payroll_type_id',
        'payroll_type',
        'adjustment_type',
        'type',
        'amount',
        'comment',
        'comment_by',
        'cost_center_id',
        'salary_overtime_date',
        'pay_period_from',
        'pay_period_to',
        'is_mark_paid',
        'is_next_payroll',
        'status',
        'ref_id',
        'user_worker_type',
        'pay_frequency',
        'is_move_to_recon',
        'is_onetime_payment',
        'one_time_payment_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(\App\Models\PayrollAdjustmentDetailLock::class, 'payroll_id', 'payroll_id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne('App\Models\user', 'id', 'user_id');
    }

    public function oneTimePaymentDetail(): HasOne
    {
        return $this->hasOne(\App\Models\OneTimePayments::class, 'id', 'one_time_payment_id');
    }

    public function saledata(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->select('pid', 'customer_name', 'customer_state', 'kw', 'net_epc', 'm1_date', 'm2_date', 'adders', 'product', 'product_id', 'gross_account_value', 'service_schedule', 'product_code');
    }

    public function commented_by(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'comment_by');
    }

    public function userData(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select(['id', 'first_name', 'last_name', 'image']);
    }

    public function workertype(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'worker_type');
    }

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_center_id');
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

    public function payrollSaleData()
    {
        return $this->hasOne(SalesMaster::class, 'pid', 'pid');
    }

    public function payrollReference()
    {
        return $this->hasOne(PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
    }

    public function payrollUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function payrollCommentedBy()
    {
        return $this->hasOne(User::class, 'id', 'comment_by');
    }
}
