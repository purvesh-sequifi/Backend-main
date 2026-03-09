<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiersLevel extends Model
{
    use HasFactory;

    protected $table = 'tiers_levels';

    protected $fillable = [
        'id',
        'tiers_schema_id',
        'level',
        'to_value',
        'from_value',
        'effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
