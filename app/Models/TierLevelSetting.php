<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TierLevelSetting extends Model
{
    use HasFactory;

    protected $table = 'tiers';

    protected $fillable = [
        'tier_type_id',
        'scale_based_on',
        'shifts_on',
        'rest',
        'tier_setting_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
