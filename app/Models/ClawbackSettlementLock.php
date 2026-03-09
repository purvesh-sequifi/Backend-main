<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClawbackSettlementLock extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'clawback_settlements_lock';

    protected $fillable = [
        'id',
        'payroll_id',
        'user_id',
        'position_id',
        'milestone_schema_id',
        'sale_user_id',
        'product_id',
        'clawback_amount',
        'clawback_type',
        'pid',
        'product_code',
        'status',
        'recon_status',
        'is_last',
        'action_status',
        'type',
        'adders_type',
        'schema_type',
        'schema_name',
        'schema_trigger',
        'during',
        'redline',
        'redline_type',
        "is_mark_paid",
        'is_next_payroll',
        'is_stop_payroll',
        'created_at',
        'is_move_to_recon',
        'is_displayed',
        'pay_period_from',
        'pay_period_to',
        'ref_id',
        'user_worker_type',
        'pay_frequency',
        'is_onetime_payment',
        'one_time_payment_id',
        'clawback_status',
        'clawback_cal_amount',
        'clawback_cal_type'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function users(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'email', 'redline', 'position_id', 'sub_position_id', 'is_manager', 'is_super_admin');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->with('recruiter');
    }

    public function userInfo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function saledata(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->select('pid', 'customer_name', 'customer_state', 'kw', 'net_epc', 'm1_date', 'm2_date', 'adders', 'product', 'product_id', 'gross_account_value', 'service_schedule', 'product_code');
    }

    public function subPosition(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function adjustment(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustmentDetail::class, 'pid', 'pid');
    }

    public function saleUserInfo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id');
    }

    public function workertype(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'worker_type');
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
        return $this->hasOne(SalesMaster::class, 'pid', 'pid')->select('pid', 'customer_name', 'customer_state', 'kw', 'net_epc', 'adders', 'product', 'gross_account_value', 'product_code');
    }

    public function payrollReference()
    {
        return $this->hasOne(PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
    }

    public function payrollUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function payrollOverUser()
    {
        return $this->hasOne(User::class, 'id', 'sale_user_id');
    }
}
