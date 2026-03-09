<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleProductMaster extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sale_product_master';

    protected $fillable = [
        'pid',
        'product_id',
        'milestone_id',
        'milestone_schema_id',
        'milestone_date',
        'tier_schema_id',
        'tier_schema_level_id',
        'type',
        'is_last_date',
        'is_exempted',
        'is_override',
        'is_projected',
        'setter1_id',
        'setter2_id',
        'closer1_id',
        'closer2_id',
        'amount',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function milestoneSchemaTrigger(): HasOne
    {
        return $this->hasOne(MilestoneSchemaTrigger::class, 'id', 'milestone_schema_id');
    }

    public function productdata(): HasOne
    {
        return $this->hasOne(\App\Models\Products::class, 'id', 'product')->where('status', 1);
    }
}
