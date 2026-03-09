<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingDealsSetting extends Model
{
    use HasFactory;

    protected $table = 'marketing__deals__settings';

    protected $fillable = [
        'reconciliation',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
