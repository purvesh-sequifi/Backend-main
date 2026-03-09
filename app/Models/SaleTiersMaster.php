<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleTiersMaster extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sale_tiers_master';

    protected $fillable = [
        'pid',
        'tier_schema_id',
        'tier_schema_level_id',
        'setter1_id',
        'setter2_id',
        'closer1_id',
        'closer2_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
