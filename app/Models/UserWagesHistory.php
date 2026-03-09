<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserWagesHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_wages_history';

    protected $fillable = [
        'user_id',
        'updater_id',
        'pay_type',
        'pay_rate',
        'pay_rate_type',
        'old_pay_rate_type',
        'pto_hours',
        'unused_pto_expires',
        'expected_weekly_hours',
        'overtime_rate',
        'effective_date',
        'effective_end_date',
        'pto_hours_effective_date',
        'old_pay_type',
        'pay_rate',
        'old_pay_rate',
        'pay_rate_type',
        'old_pay_rate_type',
        'pto_hours',
        'old_pto_hours',
        'old_unused_pto_expires',
        'old_expected_weekly_hours',
        'overtime_rate',
        'old_overtime_rate',
        'action_item_status',
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }
}
