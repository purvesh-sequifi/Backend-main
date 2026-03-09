<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BackendReconciliations extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_from',
        'period_to',
        'day_date',
        'backend_setting_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function Backend(): HasOne
    {
        return $this->hasOne(\App\Models\BackendSetting::class, 'id', 'backend_setting_id');
    }
}
