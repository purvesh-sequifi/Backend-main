<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserCommissionHistoryTiersRange extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_commission_history_tiers_ranges';

    protected $fillable = [
        'user_id',
        'user_commission_history_id',
        'tiers_schema_id',
        'tiers_levels_id',
        'value',
        'value_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tiersSchema(): HasMany
    {
        return $this->hasMany(TiersSchema::class, 'id', 'tiers_schema_id')->with('tier_metrics');
    }

    public function level(): HasOne
    {
        return $this->hasOne(TiersLevel::class, 'id', 'tiers_levels_id');
    }
}
