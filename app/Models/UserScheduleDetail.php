<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserScheduleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'schedule_id',
        'office_id',
        'schedule_from',
        'schedule_to',
        'lunch_duration',
        'work_days',
        'repeated_batch',
        'updated_by',
        'updated_type',
        'is_flexible',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
