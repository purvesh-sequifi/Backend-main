<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingEmployeeWages extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'updater_id',
        'pay_type',
        'pay_rate',
        'pto_hours',
        'unused_pto',
        'expected_weekly_hours',
        'overtime_rate',
    ];
}
