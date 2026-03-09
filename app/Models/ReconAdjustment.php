<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReconAdjustment extends Model
{
    use HasFactory;

    const USER_CLASS = \App\Models\User::class;

    protected $fillable = [
        "user_id",
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
        "sale_user_id",
        "finalize_id",
        "move_from_payroll",
        "is_onetime_payment",
        "one_time_payment_id"
    ];

    public function user(): HasOne
    {
        return $this->hasOne(self::USER_CLASS, 'id', 'user_id')->with('recruiter');
    }

    public function commentUser(): HasOne
    {
        return $this->hasOne(self::USER_CLASS, 'id', 'adjustment_by_user_id');
    }

    public function saleUserInfo(): HasOne
    {
        return $this->hasOne(self::USER_CLASS, 'id', 'sale_user_id');
    }
}
