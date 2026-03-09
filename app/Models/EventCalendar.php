<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventCalendar extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'event_calendars';

    protected $fillable = [
        'event_date',
        'event_time',
        'type',
        'state_id',
        'event_name',
        'description',
        'user_id',
        'office_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id')->select('id', 'name');
    }

    public function detailForInterview(): HasMany
    {
        return $this->hasMany(\App\Models\Lead::class, 'interview_date', 'event_date')->select('id', 'first_name', 'last_name', 'interview_date', 'interview_time');
    }
}
