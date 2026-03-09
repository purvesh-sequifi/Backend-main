<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plans extends Model
{
    use HasFactory;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        // 'monthly_charge',
        // 'annually_charge',
        // 'api_token',
        // 'password',
        'unique_pid_rack_price',
        'unique_pid_discount_price',
        'm2_rack_price',
        'm2_discount_price',
        'sclearance_plan_id',
    ];
}
