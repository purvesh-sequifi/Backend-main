<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserAdditionalOfficeOverrideHistoryTiersRange extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_additional_office_override_history_tiers_ranges';

    protected $fillable = [
        'user_id',
        'user_add_office_override_history_id',
        'tiers_schema_id',
        'tiers_levels_id',
        'value',
        'value_type',
    ];

    public function level(): HasOne
    {
        return $this->hasOne(TiersLevel::class, 'id', 'tiers_levels_id');
    }
}
