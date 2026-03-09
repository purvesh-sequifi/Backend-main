<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class OverridePoolPercentageTier extends Model
{
    use HasFactory;

    protected $table = 'override_pool_percentage_tiers';

    protected $fillable = [
        'sales_from',
        'sales_to',
        'pool_percentage',
        'is_active',
    ];

    protected $casts = [
        'sales_from'      => 'integer',
        'sales_to'        => 'integer',
        'pool_percentage' => 'float',
        'is_active'       => 'integer',
    ];

    /**
     * Scope to only return active tiers, ordered ascending by sales_from.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1)->orderBy('sales_from');
    }
}
