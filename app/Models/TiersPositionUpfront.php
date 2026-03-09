<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiersPositionUpfront extends Model
{
    use HasFactory;

    protected $table = 'tiers_position_upfronts';

    protected $fillable = [
        'id',
        'position_id',
        'position_upfront_id',
        'product_id',
        'milestone_schema_id',
        'milestone_schema_trigger_id',
        'tiers_schema_id',
        'tiers_levels_id',
        'upfront_value',
        'upfront_type',
        'effective_date',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function tiersSchema(): HasMany
    {
        return $this->hasMany(TiersSchema::class, 'id', 'tiers_schema_id');
    }

    /**
     * Get the custom sales field for this tier upfront
     */
    public function customSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
