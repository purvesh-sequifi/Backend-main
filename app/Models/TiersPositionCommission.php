<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TiersPositionCommission extends Model
{
    use HasFactory;

    protected $table = 'tiers_position_commisions';

    protected $fillable = [
        'id',
        'position_id',
        'position_commission_id',
        'product_id',
        'tiers_schema_id',
        'tiers_levels_id',
        'tiers_advancement',
        'commission_value',
        'effective_date',
        'commission_type',
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
     * Get the custom sales field for this tier commission
     */
    public function customSalesField(): BelongsTo
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
