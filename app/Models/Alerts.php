<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alerts extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $fillable = [
        'name',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function incompleteAccountAlert(): HasMany
    {
        return $this->hasMany(\App\Models\IncompleteAccountAlert::class, 'alert_id', 'id');
    }

    public function marketingDealAlert(): HasMany
    {
        return $this->hasMany(\App\Models\MarketingDealAlert::class, 'alert_id', 'id');
    }
}
