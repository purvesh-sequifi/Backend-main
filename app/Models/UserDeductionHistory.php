<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDeductionHistory extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_deduction_history';

    protected $fillable = [
        'user_id',
        'updater_id',
        'cost_center_id',
        'amount_par_paycheque',
        'old_amount_par_paycheque',
        'sub_position_id',
        'limit_value',
        'effective_date',
        'effective_end_date',
        'changes_type',
        'changes_field',
        'pay_period_from',
        'pay_period_to',
        'is_deleted',
    ];

    protected $hidden = [
        'created_at',
    ];

    public function updater(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'updater_id')->select('id', 'first_name', 'last_name', 'redline', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager');
    }

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_center_id')->select('id', 'name', 'status');
    }
}
