<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAttendanceDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'user_attendance_id',
        'adjustment_id',
        'office_id',
        'type',
        'attendance_date',
        'entry_type',
        'created_by',
        'updated_by',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
