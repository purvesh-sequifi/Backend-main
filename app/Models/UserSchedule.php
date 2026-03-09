<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'scheduled_by',
        'is_flexible',
        'is_repeat',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function userSchedulelist(): HasMany
    {
        return $this->hasMany(\App\Models\UserScheduleDetail::class, 'schedule_id', 'id');
    }
}
