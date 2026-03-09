<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollAdjustmentLock extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'payroll_adjustments_lock';

    protected $fillable = [
        'id',
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
        'comment',
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
        'hourlysalary_type',
        'hourlysalary_amount',
        'overtime_type',
        'overtime_amount'
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(\App\Models\PayrollAdjustmentDetailLock::class, 'payroll_id', 'payroll_id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne('App\Models\user', 'id', 'user_id');
    }
}
