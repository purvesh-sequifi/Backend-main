<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollAdjustmentHistory extends Model
{
    use HasFactory;

    protected $table = 'payroll_adjustment_histories';

    protected $fillable = [
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
        'reconciliations_type',
        'reconciliations_amount',
        'clawbacks_type',
        'clawbacks_amount',
        'comment',
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(\App\Models\PayrollAdjustmentDetail::class, 'payroll_id', 'payroll_id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne('App\Models\user', 'id', 'user_id');
    }
}
