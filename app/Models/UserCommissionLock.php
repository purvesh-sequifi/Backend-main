<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserCommissionLock extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_commission_lock';

    protected $fillable = [
        'id',
        'payroll_id',
        'user_id',
        'position_id',
        'product_id',
        'milestone_schema_id',
        'pid',
        'product_code',
        'amount_type',
        'schema_name',
        'schema_trigger',
        'schema_type',
        'is_last',
        'settlement_type',
        'amount',
        'redline',
        'redline_type',
        'recon_amount' ,
        'recon_amount_type',
        'net_epc' ,
        'kw',
        'date',
        'pay_period_from',
        'pay_period_to',
        'customer_signoff',
        "status",
        "recon_status",
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'is_displayed',
        'ref_id',
        'user_worker_type',
        'pay_frequency',
        'is_move_to_recon',
        'is_onetime_payment',
        'one_time_payment_id',
        'commission_amount',
        'comp_rate',
        'commission_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function userdata(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_manager', 'is_super_admin', 'status_id');
    }

    public function saledata(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->select('pid', 'customer_name', 'customer_state', 'kw', 'net_epc', 'm1_date', 'm2_date', 'adders', 'product', 'product_id', 'gross_account_value', 'service_schedule', 'product_code', 'trigger_date');
    }

    public function payroll(): HasOne
    {
        return $this->hasOne(\App\Models\Payroll::class, 'user_id', 'user_id')->select('override');
    }

    public function payrollAdjustment(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustmentLock::class, 'user_id', 'user_id');
    }

    public function subPosition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function workertype(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'worker_type');
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
}
