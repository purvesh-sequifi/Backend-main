<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBanking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'employee_id',
        'bank_name',
        'routing_number',
        'acconut_number',
        'acconut_type',
        'is_sandbox',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
