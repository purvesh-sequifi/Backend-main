<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWages extends Model
{
    use HasFactory;

    protected $table = 'user_wages';

    protected $fillable = [
        'user_id',
        'updater_id',
        'pay_type',
        'pay_rate',
        'pay_rate_type',
        'pto_hours',
        'unused_pto_expires',
        'expected_weekly_hours',
        'overtime_rate',
        'effective_date',
        'pto_hours_effective_date',
    ];
}
