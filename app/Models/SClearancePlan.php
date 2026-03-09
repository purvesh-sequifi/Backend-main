<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SClearancePlan extends Model
{
    use HasFactory;

    protected $table = 's_clearance_plans';

    protected $fillable = [
        'id',
        'bundle_id',
        'package_id',
        'plan_name',
        'price',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
