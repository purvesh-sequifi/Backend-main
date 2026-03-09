<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingDealsReconciliations extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_from',
        'period_to',
        'day_date',
        'marketing_setting_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
