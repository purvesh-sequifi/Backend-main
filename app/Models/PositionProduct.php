<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PositionProduct extends Model
{
    use HasFactory, SoftDeletes,SpatieLogsActivity;

    protected $table = 'position_products';

    protected $fillable = [
        'position_id',
        'product_id',
        'effective_date',
    ];

    public function product(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id')->with('productToMilestoneTrigger');
    }

    public function productName(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    public function productDetails(): HasOne
    {
        return $this->hasOne(Products::class, 'id', 'product_id');
    }

    public function positionDetails(): HasOne
    {
        return $this->hasOne(Positions::class, 'id', 'position_id');
    }
}
