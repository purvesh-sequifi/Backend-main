<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TierDurationSettings extends Model
{
    use HasFactory;

    protected $table = 'tiers_type';

    protected $fillable = [
        'name',
        'is_check',
        'tier_setting_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function Level(): HasOne
    {
        return $this->hasOne(\App\Models\TierLevelSetting::class, 'tier_type_id', 'id');
    }

    public function Configure(): HasMany
    {
        return $this->hasMany(\App\Models\ConfigureTier::class, 'tier_type_id', 'id');
    }
}
