<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TierSettings extends Model
{
    use HasFactory;

    protected $table = 'tier_settings';

    protected $fillable = [
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
