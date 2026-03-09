<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingDirectOverrideTiersRange extends Model
{
    use HasFactory;

    protected $table = 'onboarding_employee_direct_override_tiers_range';

    protected $fillable = [
        'user_id',
        'onboarding_direct_override_id',
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
}
