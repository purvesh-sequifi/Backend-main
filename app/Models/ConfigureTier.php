<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigureTier extends Model
{
    use HasFactory;

    protected $table = 'tiers_configure';

    protected $fillable = [
        'tier_type_id',
        'installs_to',
        'redline_shift',
        'installs_from',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
