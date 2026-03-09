<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchedulingApprovalSetting extends Model
{
    use HasFactory;

    protected $table = 'scheduling_approval_setting';

    protected $fillable = [
        'scheduling_setting',
    ];
}
