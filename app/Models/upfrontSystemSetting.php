<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class upfrontSystemSetting extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'upfront_system_settings';

    protected $fillable = [
        'upfront_for_self_gen',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
