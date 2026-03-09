<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionTier extends Model
{
    use HasFactory, SpatieLogsActivity;

    const ALL_PRODUCTS = 'All sales of this product';

    const SELECTED_PRODUCTS = 'Sales of any Product';

    const UPFRONT = 'upfront';

    const COMMISSION = 'commission';

    const OVERRIDE = 'override';

    protected $table = 'position_tiers';

    protected $fillable = [
        'position_id',
        'tiers_schema_id',
        'tier_advancement',
        'type',
        'status',
        'effective_date',
    ];

    protected $casts = [
        'status' => 'integer',
        'type' => 'string',
    ];

    // Relationships
    public function position(): BelongsTo
    {
        return $this->belongsTo(Positions::class, 'position_id');
    }

    public function tiersSchema(): BelongsTo
    {
        return $this->belongsTo(TiersSchema::class, 'tiers_schema_id');
    }
}
