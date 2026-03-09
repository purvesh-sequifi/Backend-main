<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OneTimePayments extends Model
{
    use HasFactory;

    protected $table = 'one_time_payments';

    protected $fillable = [
        'user_id',
        'req_id',
        'pay_by',
        'req_no',
        'everee_external_id',
        'everee_payment_req_id',
        'adjustment_type_id',
        'amount',
        'description',
        'pay_date',
        'everee_status',
        'payment_status',
        'everee_json_response',
        'everee_webhook_response',
        'everee_payment_status',
        'everee_paymentId',
        'from_payroll',
        'pay_frequency',
        'user_worker_type',
        'pay_period_from',
        'pay_period_to',
        'is_deposit_returned',
        'created_at',
        'updated_at',
    ];

    public function userData(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    public function paidBy(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'pay_by');
    }

    //  for everee billing module use
    public function usersdata(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }

    public function adjustment(): HasOne
    {
        return $this->hasOne(\App\Models\AdjustementType::class, 'id', 'adjustment_type_id')->select(['id', 'name']);
    }

    public function aAndR(): HasOne
    {
        return $this->hasOne(\App\Models\ApprovalsAndRequest::class, 'req_no', 'req_no');
    }

    public function frequency()
    {
        return $this->hasOne(FrequencyType::class, 'id', 'pay_frequency');
    }

    public function oneTimeUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function oneTimePayrollHistory()
    {
        return $this->hasOne(PayrollHistory::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeCommissions()
    {
        return $this->hasMany(UserCommissionLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeOverrides()
    {
        return $this->hasMany(UserOverridesLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeClawBacks()
    {
        return $this->hasMany(ClawbackSettlementLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeAdjustmentDetails()
    {
        return $this->hasMany(PayrollAdjustmentDetailLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeDeductions()
    {
        return $this->hasMany(PayrollDeductionLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeTaxDeductions()
    {
        return $this->hasMany(W2PayrollTaxDeduction::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeHourlySalary()
    {
        return $this->hasMany(PayrollHourlySalaryLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeOverTimes()
    {
        return $this->hasMany(PayrollOvertimeLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeApprovalsAndRequests()
    {
        return $this->hasMany(ApprovalsAndRequestLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeCustomFields()
    {
        return $this->hasMany(CustomFieldHistory::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeReconciliationFinalizeHistories()
    {
        return $this->hasMany(ReconciliationFinalizeHistoryLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeReconCommissionHistories()
    {
        return $this->hasMany(ReconCommissionHistoryLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeReconOverrideHistories()
    {
        return $this->hasMany(ReconOverrideHistoryLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeReconClawbackHistories()
    {
        return $this->hasMany(ReconClawbackHistoryLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeReconAdjustments()
    {
        return $this->hasMany(ReconAdjustmentLock::class, 'one_time_payment_id', 'id');
    }

    public function oneTimeReconDeductionHistories()
    {
        return $this->hasMany(ReconDeductionHistoryLock::class, 'one_time_payment_id', 'id');
    }
}
