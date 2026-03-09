<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GetPayrollData extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'get_payroll_data';

    public function usersdata(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id')->select('id', 'first_name', 'last_name', 'email', 'image', 'sub_position_id')->with('positionDetail', 'positionDeductionLimit');
    }

    public function payrollstatus(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollStatus::class, 'id', 'status');
    }

    public function payrolladjust(): HasOne
    {
        return $this->hasOne(\App\Models\PayrollAdjustment::class, 'payroll_id', 'id');
    }

    public function userDeduction(): HasMany
    {
        return $this->hasMany(\App\Models\UserDeduction::class, 'user_id', 'user_id');
    }

    public function positionDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Positions::class, 'id', 'position_id');
    }

    public function positionCommissionDeduction(): HasMany
    {
        return $this->hasMany(\App\Models\PositionCommissionDeduction::class, 'position_id', 'position_id');
    }

    public function positionDeductionLimit(): HasOne
    {
        return $this->hasOne(\App\Models\PositionsDeductionLimit::class, 'position_id', 'position_id');
    }

    public function PayrollShiftHistorie(): HasMany
    {
        return $this->hasMany(\App\Models\PayrollShiftHistorie::class, 'payroll_id', 'id');
    }
}
