<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PositionOverrideSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_id',
        'override_id',
        'sattlement_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
