<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingEmployeeRedline extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'onboarding_employee_redlines';

    protected $fillable = [
        'user_id',
        'self_gen_user',
        'updater_id',
        'position_id',
        'core_position_id',
        'redline_amount_type',
        'redline',
        'redline_type',
        'redline_effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
