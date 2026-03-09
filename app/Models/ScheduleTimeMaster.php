<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleTimeMaster extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'schedule_time_masters';

    protected $fillable = [
        'day',
        'time_slot',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
