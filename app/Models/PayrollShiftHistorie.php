<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollShiftHistorie extends Model
{
    use HasFactory;

    protected $table = 'payroll_shift_histories';

    protected $fillable = [
        'payroll_id',
        'moved_by',
        'pay_period_from',
        'pay_period_to',
        'new_pay_period_from',
        'new_pay_period_to',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
