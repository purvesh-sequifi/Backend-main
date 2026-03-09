<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeOnboardingDeduction extends Model
{
    use HasFactory;

    protected $table = 'onboarding_employee_deduction';

    protected $fillable = [
        'user_id',
        'position_id',
        'deduction_type',
        'cost_center_name',
        'cost_center_id',
        'ammount_par_paycheck',
        'deduction_setting_id',
        'pay_period_from',
        'pay_period_to',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
