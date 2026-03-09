<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReconciliationSchedule extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $fillable = [
        'period_from',
        'period_to',
        'day_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    // public function Backend() {
    //     return $this->hasOne('App\Models\BackendSetting','id','backend_setting_id');
    // }
}
