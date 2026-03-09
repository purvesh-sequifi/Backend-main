<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PositionTierOverride extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'position_tier_overrides';

    protected $fillable = [
        'position_id',
        'tier_status',
        'sliding_scale',
        'sliding_scale_locked',
        'levels',
        'level_locked',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
