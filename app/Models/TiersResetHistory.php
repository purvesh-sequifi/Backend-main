<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiersResetHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'tiers_reset_histories';

    protected $fillable = [
        'updater_id',
        'tier_schema_id',
        'tiers_type', // Progressive, Retroactive
        'start_date',
        'end_date',
        'reset_date_time',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
