<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WpUserSchedule extends Model
{
    use HasFactory;

    protected $table = 'wp_user_schedule';

    protected $fillable = [
        'user_id',
        'office_id',
        'lunch_break',
        'schedule_date',
        'day_number',
        'clock_in',
        'clock_out',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'state_id', 'id');
    }
}
