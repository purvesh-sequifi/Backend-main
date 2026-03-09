<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserScheduleTime extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_schedule_times';

    protected $fillable = [
        'user_id',
        'day',
        'time_slot',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
