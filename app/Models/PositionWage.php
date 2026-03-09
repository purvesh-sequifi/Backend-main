<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PositionWage extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_id',
        'pay_type',
        'pay_type_lock',
        'pay_rate',
        'pay_rate_type',
        'pay_rate_lock',
        'pto_hours',
        'pto_hours_lock',
        'unused_pto_expires',
        'unused_pto_expires_lock',
        'expected_weekly_hours',
        'expected_weekly_hours_lock',
        'overtime_rate',
        'overtime_rate_lock',
        'wages_status',
        'effective_date',
    ];
}
