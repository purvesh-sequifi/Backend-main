<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconCommissionHistoryLock extends Model
{
    use HasFactory;

    protected $fillable = [
        "id",
        "pid",
        "user_id",
        "finalize_id",
        "status",
        "start_date",
        "end_date",
        "sent_count",
        "finalize_count",
        "total_amount",
        "paid_amount",
        "payout",
        "payroll_execute_status",
        "pay_period_from",
        "pay_period_to",
        "is_next_payroll",
        "is_mark_paid",
        "is_displayed",
        "type",
        "schema_name",
        "schema_type",
        "is_last",
        "move_from_payroll",
        "during",
        "is_ineligible",
        "is_deducted",
        'user_worker_type',
        'pay_frequency',
        'is_onetime_payment',
        'one_time_payment_id'
    ];
}
