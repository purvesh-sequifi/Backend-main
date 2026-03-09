<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiersPositionOverrides extends Model
{
    use HasFactory;

    protected $table = 'tiers_position_overrides';

    protected $fillable = [
        'id',
        'position_id',
        'position_overrides_id',
        'product_id',
        'override_id',
        'tiers_schema_id',
        'tiers_levels_id',
        'override_value',
        'override_type',
        'effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tiersSchema(): HasMany
    {
        return $this->hasMany(TiersSchema::class, 'id', 'tiers_schema_id');
    }
}
