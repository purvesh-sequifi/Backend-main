<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApprovalsAndRequest extends Model
{
    use HasFactory, SpatieLogsActivity;

    CONST IGNORE = [7, 8, 9];

    protected $table = 'approvals_and_requests';

    protected $fillable = [
        'parent_id',
        'payroll_id',
        'employee_payroll_id',
        'req_no',
        'user_id',
        'manager_id',
        'created_by',
        'approved_by',
        'adjustment_type_id',
        'pay_period',
        'state_id',
        'dispute_type',
        'customer_pid',
        'description',
        'cost_tracking_id',
        'emi',
        'cost_date',
        'txn_id',
        'request_date',
        'pay_period_from',
        'pay_period_to',
        'amount',
        'image',
        'start_date',
        'end_date',
        'adjustment_date',
        'pto_hours_perday',
        'clock_in',
        'clock_out',
        'lunch_adjustment',
        'break_adjustment',
        'status',
        'declined_by',
        'declined_at',
        'is_mark_paid',
        'is_next_payroll',
        'ref_id',
        'pto_per_day',
        'time_adjustment_date',
        'lunch',
        'break',
        'action_item_status',
        'user_worker_type',
        'pay_frequency',
        'is_onetime_payment',
        'one_time_payment_id',
    ];

    // protected $hidden = [
    //     'created_at',
    //     'updated_at'
    // ];

    public function adjustment(): HasOne
    {
        return $this->hasOne(\App\Models\AdjustementType::class, 'id', 'adjustment_type_id');
    }

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_tracking_id');
    }

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id');
    }

    public function user()
    {
        return $this->hasone(\App\Models\User::class, 'id', 'user_id')->with('office');
    }

    public function approvedBy()
    {
        return $this->hasone(\App\Models\User::class, 'id', 'approved_by');
    }

    public function PID()
    {
        return $this->hasone(\App\Models\SalesMaster::class, 'pid', 'customer_pid');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(\App\Models\ApprovalAndRequestComment::class, 'request_id', 'id');
    }

    public function getPid(): HasMany
    {
        return $this->hasMany(\App\Models\RequestApprovelByPid::class, 'request_id', 'id');
    }

    public function userComment(): HasOne
    {
        return $this->hasOne(\App\Models\ApprovalAndRequestComment::class, 'user_id', 'id');
    }

    public function userData(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->select(['id', 'first_name', 'last_name', 'image']);
    }

    public function payrollcommon(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollCommon::class, 'id', 'ref_id')->whereNotNull('orig_payfrom');
    }

    public function ChildApprovalsAndRequests(): HasOne
    {
        return $this->hasOne(ApprovalsAndRequest::class, 'id', 'parent_id');
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

    public function payrollAdjustment()
    {
        return $this->hasOne(AdjustementType::class, 'id', 'adjustment_type_id');
    }

    public function payrollComments()
    {
        return $this->hasMany(ApprovalAndRequestComment::class, 'request_id', 'id');
    }

    public function payrollCommentedBy()
    {
        return $this->hasOne(User::class, 'id', 'approved_by');
    }

    public function payrollCostCenter()
    {
        return $this->hasOne(CostCenter::class, 'id', 'cost_tracking_id');
    }
}
