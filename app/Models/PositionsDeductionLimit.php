<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PositionsDeductionLimit extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'positions_duduction_limits';

    protected $fillable = [
        'deduction_setting_id',
        'position_id',
        'status',
        'limit_type',
        'limit_ammount',
        'limit',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deduction_setting_id',
        'position_id',
    ];
}
