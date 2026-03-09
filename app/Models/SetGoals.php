<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SetGoals extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'set_goals';

    protected $fillable = [
        'user_id',
        'earning',
        'account',
        'kw_sold',
        'start_date',
        'end_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
