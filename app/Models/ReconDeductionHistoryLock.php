<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconDeductionHistoryLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
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
}
