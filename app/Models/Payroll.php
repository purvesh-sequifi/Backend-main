<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payroll extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $fillable = [
        'id',
        'user_id',
        'position_id',
        'commission',
        'override',
        'reimbursement',
        'clawback',
        'deduction',
        'adjustment',
        'reconciliation',
        'hourly_salary',
        'overtime',
        'net_pay',
        'gross_pay',
        'subtract_amount',
        'comment',
        'pay_period_from',
        'pay_period_to',
        'status', // 1 = PENDING, 2 = SUCCESS, 3 = EXECUTED
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'everee_message',
        'finalize_status', // 0 = PENDING, 1 = IN JOB, 2 = SUCCESS, 3 = FAILED FROM EVEREE
        'everee_external_id',
        'custom_payment',
        'ref_id',
        'is_onetime_payment',
        'one_time_payment_id',
        'pay_frequency',
        'worker_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function usersdata(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'email', 'image', 'sub_position_id', 'entity_type', 'social_sequrity_no', 'business_name', 'business_ein', 'employee_id', 'everee_workerId', 'is_super_admin', 'is_manager', 'position_id', 'worker_type', 'office_id', 'everee_embed_onboard_profile', 'onboardProcess', 'stop_payroll')->with('positionDetail', 'positionDeductionLimit');
    }

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function positionCommissionDeduction(): HasMany
    {
        return $this->hasMany(\App\Models\PositionCommissionDeduction::class, 'position_id', 'position_id');
    }

    public function positionDeductionLimit(): HasOne
    {
        return $this->hasOne(\App\Models\PositionsDeductionLimit::class, 'position_id', 'position_id');
    }

    public function payrollstatus(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollStatus::class, 'id', 'status');
    }

    public function payrolladjust(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustment::class, 'payroll_id', 'id');
    }

    public function userDeduction(): HasMany
    {
        return $this->hasMany(\App\Models\UserDeduction::class, 'user_id', 'user_id');
    }

    public function PayrollShiftHistorie(): HasMany
    {
        return $this->hasMany(\App\Models\PayrollShiftHistorie::class, 'payroll_id', 'id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(\App\Models\ApprovalsAndRequest::class, 'user_id', 'user_id')->where('status', 'Approved')->whereNotNull('req_no')->whereNotIn('adjustment_type_id', [7, 8, 9]);
    }

    public function reconciliationInfo(): HasOne
    {
        return $this->hasOne(\App\Models\ReconciliationFinalizeHistory::class, 'payroll_id', 'id');
    }

    public function reconciliationFinalizeHistories(): HasMany
    {
        return $this->hasMany(\App\Models\ReconciliationFinalizeHistory::class, 'payroll_id', 'id');
    }

    public function userCommission(): HasMany
    {
        return $this->hasMany(UserCommission::class, 'payroll_id', 'id');
    }

    public function userDeductions(): HasMany
    {
        return $this->hasMany(PayrollDeductions::class, 'payroll_id', 'id');
    }

    public function userOverride(): HasMany
    {
        return $this->hasMany(UserOverrides::class, 'payroll_id', 'id');
    }

    public function userClawback(): HasMany
    {
        return $this->hasMany(ClawbackSettlement::class, 'payroll_id', 'id');
    }

    public function userApproveRequest(): HasMany
    {
        return $this->hasMany(ApprovalsAndRequest::class, 'payroll_id', 'id')->whereNotIn('adjustment_type_id', [2, 7, 8, 9]);
    }

    public function userApproveRequestReimbursement(): HasMany
    {
        return $this->hasMany(ApprovalsAndRequest::class, 'payroll_id', 'id')->whereIn('adjustment_type_id', [2]);
    }

    public function userApproveRequestAll(): HasMany
    {
        return $this->hasMany(ApprovalsAndRequest::class, 'payroll_id', 'id');
    }

    public function userPayrollAdjustmentDetails(): HasMany
    {
        return $this->hasMany(PayrollAdjustmentDetail::class, 'payroll_id', 'id');
    }

    public function payrollAdjustmentDetails(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustmentDetail::class, 'payroll_id', 'id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class, 'payroll_id', 'id');

    }

    public function userApproveRequestBonus(): HasMany
    {
        return $this->hasMany(ApprovalsAndRequest::class, 'payroll_id', 'id')->whereIn('adjustment_type_id', [3]);
    }

    public function payroll(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function scopeWithinDateRange(Builder $query, $startDate, $endDate)
    {
        return $query->whereBetween('pay_period_to', [$startDate, $endDate])
            ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
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
            $query->whereBetween('payrolls.pay_period_from', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereBetween('payrolls.pay_period_to', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereColumn('payrolls.pay_period_from', 'payrolls.pay_period_to');
        }, function ($query) use ($param) {
            $query->where([
                'payrolls.pay_period_from' => $param['pay_period_from'],
                'payrolls.pay_period_to' => $param['pay_period_to'],
            ]);
        });

        $query->where(['payrolls.worker_type' => $param['worker_type'], 'payrolls.pay_frequency' => $param['pay_frequency']]);

        // Apply additional where conditions if provided
        if (!empty($additionalWhere)) {
            $query->where($additionalWhere);
        }

        return $query;
    }

    public function payrollCustomFields()
    {
        return $this->hasMany(CustomField::class, 'payroll_id', 'id');
    }

    public function payrollUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function payrollSalary()
    {
        return $this->hasOne(PayrollHourlySalary::class, 'payroll_id', 'id');
    }

    public function payrollOvertime()
    {
        return $this->hasOne(PayrollOvertime::class, 'payroll_id', 'id');
    }

    public function payrollCommission()
    {
        return $this->hasOne(UserCommission::class, 'payroll_id', 'id');
    }

    public function payrollOverride()
    {
        return $this->hasOne(UserOverrides::class, 'payroll_id', 'id');
    }

    public function payrollClawBack()
    {
        return $this->hasMany(ClawbackSettlement::class, 'payroll_id', 'id');
    }

    public function userRequestApprove()
    {
        return $this->hasMany(ApprovalsAndRequest::class, 'user_id', 'user_id');
    }

    public function payrollApproveRequest()
    {
        return $this->hasMany(ApprovalsAndRequest::class, 'payroll_id', 'id');
    }

    public function payrollPayrollAdjustmentDetails()
    {
        return $this->hasMany(PayrollAdjustmentDetail::class, 'payroll_id', 'id');
    }

    public function payrollDeductions()
    {
        return $this->hasMany(PayrollDeductions::class, 'payroll_id', 'id');
    }

    public function payrollReconciliation()
    {
        return $this->hasOne(ReconciliationFinalizeHistory::class, 'payroll_id', 'id');
    }

    public function payrollSalaries()
    {
        return $this->hasMany(PayrollHourlySalary::class, 'payroll_id', 'id');
    }

    public function payrollOvertimes()
    {
        return $this->hasMany(PayrollOvertime::class, 'payroll_id', 'id');
    }

    public function payrollCommissions()
    {
        return $this->hasMany(UserCommission::class, 'payroll_id', 'id');
    }

    public function payrollOverrides()
    {
        return $this->hasMany(UserOverrides::class, 'payroll_id', 'id');
    }

    public function payrollClawBacks()
    {
        return $this->hasMany(ClawbackSettlement::class, 'payroll_id', 'id');
    }

    public function payrollReconciliations()
    {
        return $this->hasMany(ReconciliationFinalizeHistory::class, 'payroll_id', 'id');
    }
}
