<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconAdjustmentLock extends Model
{
    use HasFactory;

    protected $fillable = [
        "id",
        "user_id",
        "finalize_id",
        "pid",
        "start_date",
        "end_date",
        "adjustment_type",
        "adjustment_override_type",
        "adjustment_amount",
        "adjustment_comment",
        "adjustment_by_user_id",
        "payroll_status",
        "finalize_count",
        "sent_count",
        "payroll_id",
        "pay_period_from",
        "pay_period_to",
        "payroll_execute_status",
        "is_onetime_payment",
        "one_time_payment_id"
    ];
}
