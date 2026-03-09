<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersTiersHistory extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'users_tiers_histories';

    protected $fillable = [
        'user_id',
        'product_id',
        'tiers_history_id',
        'tier_schema_id',
        'tier_schema_level_id',
        'next_tier_schema_level_id',
        'office_id',
        'type', // Commission, Upfront, Override
        'sub_type', // Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)
        'current_value',
        'remaining_value',
        'current_level',
        'remaining_level',
        'reset_date_time',
        'maxed', // 0 = NOT MAXED, 1 = MAXED
        'status', // 1 = ENABLED, 0 = DISABLED
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
