<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserIndirectOverrideHistoryTiersRange extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_indirect_override_history_tiers_ranges';

    protected $fillable = [
        'user_id',
        'user_override_history_id',
        'tiers_schema_id',
        'tiers_levels_id',
        'value',
        'value_type',
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
