<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OverridesType extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'overrides__types';

    protected $fillable = [
        'overrides_type',
        'lock_pay_out_type',
        'max_limit',
        'parsonnel_limit',
        'min_position',
        'level',
        'is_check',
        'override_setting_id',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
