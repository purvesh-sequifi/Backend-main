<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconDeductionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'user_id',
        'cost_center_id',
        'finalize_id',
        'amount',
        'limit',
        'total',
        'outstanding',
        'subtotal',
        'start_date',
        'end_date',
        'status',
        'finalize_count',
        'pay_period_from',
        'pay_period_to',
        'payroll_executed_status',
        'is_mark_paid',
        'is_next_payroll',
        'is_stop_payroll',
        'is_onetime_payment',
        'one_time_payment_id'
    ];

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_center_id');
    }
}
