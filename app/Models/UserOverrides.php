<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserOverrides extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_overrides';

    protected $fillable = [
        'payroll_id',
        'user_id',
        'worker_type',
        'product_id',
        'type',
        'during' ,
        'sale_user_id',
        'pid',
        'product_code',
        'net_epc',
        'kw',
        'amount',
        'overrides_amount',
        'overrides_type',
        'adjustment_amount',
        'comment',
        'calculated_redline',
        'calculated_redline_type',
        'overrides_settlement_type',
        'pay_period_from',
        'pay_period_to',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'is_displayed',
        'status',
        'recon_status',
        'office_id',
        'ref_id',
        "user_worker_type",
        'pay_frequency',
        'is_move_to_recon',
        'is_onetime_payment',
        'one_time_payment_id',
        'worker_type',
        'created_at',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        // 'created_at',
        'updated_at',
    ];

    public function userInfo(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'is_manager', 'is_super_admin', 'stop_payroll', 'terminate', 'dismiss');
    }

    public function userdata(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'position_id', 'is_manager', 'is_super_admin', 'stop_payroll', 'worker_type');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->with('recruiter')->where('dismiss', 0);
    }

    public function userpayrolloverride(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'sale_user_id'); // ->with('recruiter');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function payrollAdjustments(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustment::class, 'user_id', 'user_id');
    }

    public function payrollcommon(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
    }

    public function payrollAdjustmentAmount(): HasOne
    {
        return $this->hasOne(PayrollAdjustmentDetail::class, 'payroll_id', 'payroll_id')
            ->where('user_id', $this->user_id)
            ->where('payroll_type', 'overrides')
            ->where('pid', $this->pid);
    }

    public function saledata(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->select('pid', 'customer_name', 'customer_state', 'kw', 'net_epc', 'm1_date', 'm2_date', 'adders', 'product', 'gross_account_value', 'scheduled_install', 'service_schedule', 'product_code');
    }

    public function overrideUser(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'is_manager', 'is_super_admin', 'stop_payroll', 'terminate', 'dismiss');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
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

    public function payrollOverUser()
    {
        return $this->hasOne(User::class, 'id', 'sale_user_id');
    }

    public function overrideStatus()
    {
        return $this->hasOne('App\Models\OverrideStatus', 'user_id', 'sale_user_id')
            ->where('recruiter_id', $this->user_id)
            ->where('type', $this->type)
            ->where(function($query) {
                $query->where('product_id', $this->product_id)
                      ->orWhere('product_id', 0);
            })
            ->orderByRaw('CASE WHEN product_id = ? THEN 0 ELSE 1 END', [$this->product_id]);
    }

    /**
     * Get the custom sales field for this override
     */
    public function customSalesField(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
