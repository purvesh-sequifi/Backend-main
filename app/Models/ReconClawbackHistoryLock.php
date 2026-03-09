<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconClawbackHistoryLock extends Model
{
    use HasFactory;

    protected $fillable = [
        "id",
        "pid",
        "user_id",
        "finalize_id",
        "start_date",
        "end_date",
        "status",
        "type",
        "move_from_payroll",
        "sent_count",
        "finalize_count",
        "total_amount",
        "paid_amount",
        "payout",
        "payroll_execute_status",
        "pay_period_from",
        "pay_period_to",
        "payroll_id",
        "ref_id",
        "is_next_payroll",
        "is_mark_paid",
        "is_displayed",
        "sale_user_id",
        "adders_type",
        "during",
        "is_onetime_payment",
        "one_time_payment_id"
    ];
}
