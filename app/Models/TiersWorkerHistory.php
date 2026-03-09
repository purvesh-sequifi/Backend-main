<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiersWorkerHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'tiers_worker_histories';

    protected $fillable = [
        'user_lead_id',
        'tier_schema_id',
        'tier_schema_level_id',
        'tiers_type', // Progressive, Retroactive
        'tiers_metrics',
        'type', // User, Lead, Manager
        'reset_date_time',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
