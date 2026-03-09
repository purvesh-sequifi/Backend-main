<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PositionCommissionDeductionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'position_id',
        'deducation_locked',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
