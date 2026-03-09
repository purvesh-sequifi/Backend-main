<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class overrideSystemSetting extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'override_system_settings';

    protected $fillable = [
        'allow_manual_override_status',
        'allow_office_stack_override_status',
        'pay_type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
