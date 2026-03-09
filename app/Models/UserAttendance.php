<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAttendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'current_time',
        'lunch_time',
        'break_time',
        'everee_shift_ids',
        'is_synced',
        'date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function userattendancelist(): HasMany
    {
        return $this->hasMany(\App\Models\UserAttendanceDetail::class, 'user_attendance_id', 'id');
    }

    public function attendencewithoutlogout(): HasMany
    {
        return $this->hasMany(\App\Models\UserAttendanceDetail::class, 'user_attendance_id', 'id')->where('type', '!=', 'clock out');
    }
}
