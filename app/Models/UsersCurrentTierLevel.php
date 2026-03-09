<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsersCurrentTierLevel extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'users_current_tier_level';

    protected $fillable = [
        'user_id',
        'product_id',
        'tier_schema_id',
        'tier_schema_level_id',
        'next_tier_schema_level_id',
        'office_id',
        'tiers_type', // Progressive, Retroactive
        'type', // Commission, Upfront, Override
        'sub_type', // Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)
        'current_value',
        'remaining_value',
        'current_level',
        'remaining_level',
        'maxed', // 0 = NOT MAXED, 1 = MAXED
        'status', // 1 = ENABLED, 0 = DISABLED
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
