<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleTiersDetail extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sale_tiers_details';

    protected $fillable = [
        'pid',
        'product_id',
        'schema_id',
        'user_id',
        'office_id',
        'tier_level',
        'is_tiered', // 0 = Non Tier, 1 = Tiered
        'tiers_type', // Progressive, Retroactive
        'type', // Commission, Upfront, Override
        'sub_type', // Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)
        'is_locked', // 0 = Not Locked, 1 = Locked
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
