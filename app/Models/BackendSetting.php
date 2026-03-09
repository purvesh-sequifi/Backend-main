<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackendSetting extends Model
{
    use HasFactory;

    protected $table = 'backend_settings';

    protected $fillable = [
        'commission_withheld',
        'maximum_withheld',
        'commission_type',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function Backend(): HasMany
    {
        return $this->hasMany(\App\Models\BackendReconciliations::class, 'backend_setting_id', 'id');
    }
}
