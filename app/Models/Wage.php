<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wage extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_id',
        'wages_status',
        'pay_type',
        'pay_type_lock_for_hire',
        'pay_rate',
        'pay_rate_lock_for_hire',
        'pto_hours',
        'pto_hours_lock_for_hire',
        'pay_type',
        'unused_pto_lock_for_hire',
        'expected_weekly_hours',
        'ewh_lock_for_hire',
        'overtime_rate',
        'ot_rate_lock_for_hire',
    ];
}
