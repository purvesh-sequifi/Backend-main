<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MarketingDealAlert extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $fillable = [
        'alert_id',
        'alert_type',
        'department_id',
        'position_id',
        'personnel_id',
        'amount',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function department(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'department_id');
    }

    public function position(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'position_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'personnel_id');
    }
}
