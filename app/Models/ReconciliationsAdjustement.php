<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconciliationsAdjustement extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'reconciliations_adjustement';

    protected $fillable = [
        'reconciliation_id',
        'payroll_id',
        'sent_count',
        'user_id',
        'adjustment_type',
        'type',
        'payroll_move_status',
        'commission_due',
        'overrides_due',
        'override_type',
        'pid',
        'clawback_due',
        'reimbursement',
        'deduction',
        'adjustment',
        'reconciliation',
        'payroll_status',
        'start_date',
        'end_date',
        'pay_period_from',
        'pay_period_to',
        'comment',
        'created_at',
        'comment_by',
        'payroll_execute_status',
    ];

    protected $hidden = [

        'updated_at',
    ];

    public function reconciliationDetail(): HasOne
    {
        return $this->hasOne(\App\Models\UserReconciliationCommission::class, 'id', 'reconciliation_id');
    }

    public function reconciliationInfo(): HasOne
    {
        return $this->hasOne(\App\Models\UserReconciliationWithholding::class, 'pid', 'pid');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id')->with('recruiter');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid');
    }

    public function commentUser(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'comment_by');
    }
}
