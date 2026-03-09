<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'payroll_history';

    protected $fillable = [
        'payroll_id',
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
        'status',
        'is_mark_paid',
        'is_next_payroll',
        'finalize_status',
        'everee_message',
        'is_stop_payroll',
        'deduction_details',
        'ref_id',
        'pay_frequency_date',
        'pay_period_from',
        'pay_period_to',
        'pay_type',
        'everee_status',
        'everee_payment_requestId',
        'everee_external_id',
        'everee_paymentId',
        'everee_json_response',
        'everee_payment_status',
        'everee_webhook_json',
        'custom_payment',
        'created_at',
        'updated_at',
        'is_onetime_payment',
        'one_time_payment_id',
        'worker_type',
        'pay_frequency',
        'is_deposit_returned'
    ];

    protected $hidden = [
        // 'created_at',
        //  'updated_at'
    ];

    public function usersdata(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->withoutGlobalScopes()->with('positionDetail');
    }

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function payrollstatus(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollStatus::class, 'id', 'status');
    }

    public function payroll(): HasOne
    {
        return $this->hasOne(\App\Models\Payroll::class, 'user_id', 'user_id')->select('id', 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWithinDateRange(Builder $query, $startDate, $endDate)
    {
        return $query->whereBetween('pay_period_to', [$startDate, $endDate])
            ->orWhereBetween('pay_period_from', [$startDate, $endDate]);
    }

    public function workertype(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'worker_type')->withoutGlobalScopes();
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
            $query->whereBetween('payroll_history.pay_period_from', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereBetween('payroll_history.pay_period_to', [$param['pay_period_from'], $param['pay_period_to']])
                ->whereColumn('payroll_history.pay_period_from', 'payroll_history.pay_period_to');
        }, function ($query) use ($param) {
            $query->where([
                'payroll_history.pay_period_from' => $param['pay_period_from'],
                'payroll_history.pay_period_to' => $param['pay_period_to'],
            ]);
        });

        $query->where(['payroll_history.worker_type' => $param['worker_type'], 'payroll_history.pay_frequency' => $param['pay_frequency']]);

        // Apply additional where conditions if provided
        if (!empty($additionalWhere)) {
            $query->where($additionalWhere);
        }

        return $query;
    }

    public function payrollCustomFields()
    {
        return $this->hasMany(CustomFieldHistory::class, 'payroll_id', 'payroll_id');
    }

    public function payrollUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function payrollSalary()
    {
        return $this->hasOne(PayrollHourlySalaryLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollOvertime()
    {
        return $this->hasOne(PayrollOvertimeLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollCommission()
    {
        return $this->hasOne(UserCommissionLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollOverride()
    {
        return $this->hasOne(UserOverridesLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollClawBack()
    {
        return $this->hasMany(ClawbackSettlementLock::class, 'payroll_id', 'payroll_id');
    }

    public function userRequestApprove()
    {
        return $this->hasMany(ApprovalsAndRequestLock::class, 'user_id', 'user_id');
    }

    public function payrollApproveRequest()
    {
        return $this->hasMany(ApprovalsAndRequestLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollPayrollAdjustmentDetails()
    {
        return $this->hasMany(PayrollAdjustmentDetailLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollDeductions()
    {
        return $this->hasMany(PayrollDeductionLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollReconciliation()
    {
        return $this->hasOne(ReconciliationFinalizeHistoryLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollSalaries()
    {
        return $this->hasOne(PayrollHourlySalaryLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollOvertimes()
    {
        return $this->hasOne(PayrollOvertimeLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollCommissions()
    {
        return $this->hasMany(UserCommissionLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollOverrides()
    {
        return $this->hasMany(UserOverridesLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollClawBacks()
    {
        return $this->hasMany(ClawbackSettlementLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollReconciliations()
    {
        return $this->hasMany(ReconciliationFinalizeHistoryLock::class, 'payroll_id', 'payroll_id');
    }

    public function payrollFrequency()
    {
        return $this->hasOne(FrequencyType::class, 'id', 'pay_frequency');
    }
}
