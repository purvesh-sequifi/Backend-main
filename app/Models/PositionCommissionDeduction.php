<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PositionCommissionDeduction extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'position_commission_deductions';

    protected $fillable = [
        'deduction_setting_id',
        'position_id',
        'cost_center_id',
        'deduction_type',
        'ammount_par_paycheck',
        'changes_type',
        'changes_field',
        'pay_period_from',
        'pay_period_to',
        'effective_date',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function costcenter(): HasOne
    {
        return $this->hasOne(\App\Models\CostCenter::class, 'id', 'cost_center_id')->select('id', 'name', 'status');
    }
}
