<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmSetting extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'crm_setting';

    protected $fillable = [
        'crm_id',
        'company_id',
        'value',
        'plan_name',
        'amount_per_job',
        'status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
