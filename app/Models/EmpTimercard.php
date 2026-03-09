<?php

namespace App\Models;

// use AWS\CRT\Log;
use Illuminate\Database\Eloquent\Model;

class EmpTimercard extends Model
{
    protected $table = 'emp_timer_card';

    // public $search;

    protected $fillable = [
        'id',
        'user_id',
        'office_id',
        'date',
        'start',
        'end',
        'type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
