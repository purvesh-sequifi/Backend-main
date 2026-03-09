<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconOverrideHistoryLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'finalize_id',
        'pid',
        'start_date',
        'end_date',
        'customer_name',
        'overrider',
        'type',
        'kw',
        'override_amount',
        'total_amount',
        'paid',
        'percentage',
        'status',
        'sent_count',
        "payroll_execute_status",
        "pay_period_from",
        "pay_period_to",
        "payroll_id",
        "is_next_payroll",
        "is_mark_paid",
        "is_displayed",
        "override_id",
        "finalize_count",
        "ref_id",
        "move_from_payroll",
        "during",
        "overrides_settlement_type",
        "is_ineligible",
        'user_worker_type',
        'pay_frequency',
        'is_onetime_payment',
        'one_time_payment_id'
    ];
}
