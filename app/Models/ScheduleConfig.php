<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleConfig extends Model
{
    use HasFactory;

    protected $table = 'scheduling_configuration';

    protected $fillable = [
        'clock_format',
        'default_lunch_dutration',
    ];
}
